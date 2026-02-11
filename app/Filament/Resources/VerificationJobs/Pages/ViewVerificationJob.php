<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Enums\VerificationJobStatus;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Jobs\FinalizeVerificationJob;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\VerificationJob;
use App\Services\SeedSend\SeedSendCampaignService;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ViewVerificationJob extends ViewRecord
{
    protected static string $resource = VerificationJobResource::class;

    /**
     * @return array<class-string<Action>>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label('Finalize')
                ->action(function (): void {
                    $record = $this->record;

                    FinalizeVerificationJob::dispatch($record->id);

                    $record->addLog('finalize_queued', 'Finalization queued by admin.', [], auth()->id());
                    AdminAuditLogger::log('job_finalize_queued', $record, []);
                })
                ->requiresConfirmation()
                ->disabled(function (): bool {
                    $record = $this->record;
                    $hasFinalKeys = filled($record->valid_key) && filled($record->invalid_key) && filled($record->risky_key);

                    if ($record->status === VerificationJobStatus::Completed && $hasFinalKeys) {
                        return true;
                    }

                    if ($record->status === VerificationJobStatus::Failed) {
                        return true;
                    }

                    if ($record->status === VerificationJobStatus::Pending) {
                        return true;
                    }

                    if ($record->status === VerificationJobStatus::Processing) {
                        return $record->chunks()
                            ->where('status', '!=', 'completed')
                            ->exists();
                    }

                    return false;
                })
                ->tooltip(function (): ?string {
                    $record = $this->record;
                    $hasFinalKeys = filled($record->valid_key) && filled($record->invalid_key) && filled($record->risky_key);

                    if ($record->status === VerificationJobStatus::Completed && $hasFinalKeys) {
                        return 'Final outputs already generated.';
                    }

                    if ($record->status === VerificationJobStatus::Failed) {
                        return 'Failed jobs cannot be finalized.';
                    }

                    if ($record->status === VerificationJobStatus::Pending) {
                        return 'Job has not started processing.';
                    }

                    if ($record->status === VerificationJobStatus::Processing) {
                        $hasIncompleteChunks = $record->chunks()
                            ->where('status', '!=', 'completed')
                            ->exists();

                        if ($hasIncompleteChunks) {
                            return 'Finalize once all chunks are completed.';
                        }
                    }

                    return null;
                })
                ->successNotificationTitle('Finalization queued')
                ->visible(function (): bool {
                    $record = $this->record;

                    if ($record->status === VerificationJobStatus::Completed) {
                        $hasFinalKeys = filled($record->valid_key) && filled($record->invalid_key) && filled($record->risky_key);

                        return ! $hasFinalKeys;
                    }

                    return true;
                }),
            Action::make('requeue_failed_chunks')
                ->label('Requeue Failed Chunks')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->record;
                    $affected = $record->chunks()
                        ->where('status', 'failed')
                        ->update([
                            'status' => 'pending',
                            'attempts' => DB::raw('attempts + 1'),
                            'claimed_at' => null,
                            'claim_expires_at' => null,
                            'claim_token' => null,
                            'engine_server_id' => null,
                            'assigned_worker_id' => null,
                            'output_disk' => null,
                            'valid_key' => null,
                            'invalid_key' => null,
                            'risky_key' => null,
                            'valid_count' => null,
                            'invalid_count' => null,
                            'risky_count' => null,
                        ]);

                    if ($affected === 0) {
                        Notification::make()
                            ->title('No failed chunks to requeue.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $record->addLog('chunks_requeued', 'Failed chunks requeued by admin.', [
                        'count' => $affected,
                    ], auth()->id());

                    AdminAuditLogger::log('chunks_requeued', $record, [
                        'count' => $affected,
                        'status' => 'failed',
                    ]);

                    Notification::make()
                        ->title("Requeued {$affected} failed chunks.")
                        ->success()
                        ->send();
                }),
            Action::make('requeue_stuck_chunks')
                ->label('Requeue Stuck Chunks')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->record;
                    $affected = $record->chunks()
                        ->where('status', 'processing')
                        ->whereNotNull('claim_expires_at')
                        ->where('claim_expires_at', '<', now())
                        ->update([
                            'status' => 'pending',
                            'attempts' => DB::raw('attempts + 1'),
                            'claimed_at' => null,
                            'claim_expires_at' => null,
                            'claim_token' => null,
                            'engine_server_id' => null,
                            'assigned_worker_id' => null,
                            'output_disk' => null,
                            'valid_key' => null,
                            'invalid_key' => null,
                            'risky_key' => null,
                            'valid_count' => null,
                            'invalid_count' => null,
                            'risky_count' => null,
                        ]);

                    if ($affected === 0) {
                        Notification::make()
                            ->title('No stuck chunks to requeue.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $record->addLog('chunks_requeued', 'Stuck chunks requeued by admin.', [
                        'count' => $affected,
                        'reason' => 'lease_expired',
                    ], auth()->id());

                    AdminAuditLogger::log('chunks_requeued', $record, [
                        'count' => $affected,
                        'reason' => 'lease_expired',
                    ]);

                    Notification::make()
                        ->title("Requeued {$affected} stuck chunks.")
                        ->success()
                        ->send();
                }),
            Action::make('mark_failed')
                ->label('Mark Job Failed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (VerificationJob $record): bool => $record->status !== VerificationJobStatus::Failed)
                ->action(function (): void {
                    $record = $this->record;

                    $record->update([
                        'status' => VerificationJobStatus::Failed,
                        'error_message' => 'Marked failed by admin.',
                        'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                        'failure_code' => 'manual_fail',
                        'finished_at' => now(),
                    ]);

                    $record->addLog('failed', 'Job marked failed by admin.', [
                        'reason' => 'manual_fail',
                    ], auth()->id());

                    AdminAuditLogger::log('job_mark_failed', $record, [
                        'reason' => 'manual_fail',
                    ]);

                    Notification::make()
                        ->title('Job marked as failed.')
                        ->success()
                        ->send();
                }),
            Action::make('approve_seed_send_consent')
                ->label('Approve SG6 Consent')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    return $this->record->seedSendConsents()
                        ->where('status', SeedSendConsent::STATUS_REQUESTED)
                        ->exists();
                })
                ->action(function (): void {
                    $consent = $this->record->seedSendConsents()
                        ->where('status', SeedSendConsent::STATUS_REQUESTED)
                        ->latest('id')
                        ->first();

                    if (! $consent) {
                        Notification::make()
                            ->title('No pending SG6 consent found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    app(SeedSendCampaignService::class)->approveConsent($consent, auth()->user());

                    Notification::make()
                        ->title('SG6 consent approved.')
                        ->success()
                        ->send();
                }),
            Action::make('revoke_seed_send_consent')
                ->label('Revoke SG6 Consent')
                ->color('danger')
                ->form([
                    TextInput::make('reason')
                        ->label('Revocation reason')
                        ->maxLength(500),
                ])
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    return $this->record->seedSendConsents()
                        ->where('status', SeedSendConsent::STATUS_APPROVED)
                        ->whereNull('revoked_at')
                        ->exists();
                })
                ->action(function (array $data): void {
                    $consent = $this->record->seedSendConsents()
                        ->where('status', SeedSendConsent::STATUS_APPROVED)
                        ->whereNull('revoked_at')
                        ->latest('id')
                        ->first();

                    if (! $consent) {
                        Notification::make()
                            ->title('No active SG6 consent found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(SeedSendCampaignService::class)->revokeConsent($consent, auth()->user(), $data['reason'] ?? null);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('SG6 consent revoked.')
                        ->success()
                        ->send();
                }),
            Action::make('start_seed_send_campaign')
                ->label('Start SG6 Campaign')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (bool) config('seed_send.enabled', false) && $this->record->status === VerificationJobStatus::Completed)
                ->action(function (): void {
                    $consent = $this->record->seedSendConsents()
                        ->where('status', SeedSendConsent::STATUS_APPROVED)
                        ->latest('id')
                        ->first();

                    if (! $consent) {
                        Notification::make()
                            ->title('Approve SG6 consent first.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(SeedSendCampaignService::class)->startCampaign($this->record, $consent, auth()->user());
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('SG6 campaign queued.')
                        ->success()
                        ->send();
                }),
            Action::make('pause_seed_send_campaign')
                ->label('Pause SG6 Campaign')
                ->color('gray')
                ->form([
                    TextInput::make('reason')
                        ->label('Pause reason')
                        ->maxLength(255),
                ])
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    return $this->record->seedSendCampaigns()
                        ->whereIn('status', [SeedSendCampaign::STATUS_RUNNING, SeedSendCampaign::STATUS_QUEUED])
                        ->exists();
                })
                ->action(function (array $data): void {
                    $campaign = $this->record->seedSendCampaigns()
                        ->whereIn('status', [SeedSendCampaign::STATUS_RUNNING, SeedSendCampaign::STATUS_QUEUED])
                        ->latest('created_at')
                        ->first();

                    if (! $campaign) {
                        Notification::make()
                            ->title('No running SG6 campaign found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(SeedSendCampaignService::class)->pauseCampaign($campaign, auth()->user(), $data['reason'] ?? null);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('SG6 campaign paused.')
                        ->success()
                        ->send();
                }),
            Action::make('resume_seed_send_campaign')
                ->label('Resume SG6 Campaign')
                ->color('info')
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    return $this->record->seedSendCampaigns()
                        ->where('status', SeedSendCampaign::STATUS_PAUSED)
                        ->exists();
                })
                ->action(function (): void {
                    $campaign = $this->record->seedSendCampaigns()
                        ->where('status', SeedSendCampaign::STATUS_PAUSED)
                        ->latest('created_at')
                        ->first();

                    if (! $campaign) {
                        Notification::make()
                            ->title('No paused SG6 campaign found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(SeedSendCampaignService::class)->resumeCampaign($campaign, auth()->user());
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('SG6 campaign resumed.')
                        ->success()
                        ->send();
                }),
            Action::make('cancel_seed_send_campaign')
                ->label('Cancel SG6 Campaign')
                ->color('danger')
                ->form([
                    TextInput::make('reason')
                        ->label('Cancel reason')
                        ->maxLength(255),
                ])
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    return $this->record->seedSendCampaigns()
                        ->whereIn('status', [SeedSendCampaign::STATUS_QUEUED, SeedSendCampaign::STATUS_RUNNING, SeedSendCampaign::STATUS_PAUSED])
                        ->exists();
                })
                ->action(function (array $data): void {
                    $campaign = $this->record->seedSendCampaigns()
                        ->whereIn('status', [SeedSendCampaign::STATUS_QUEUED, SeedSendCampaign::STATUS_RUNNING, SeedSendCampaign::STATUS_PAUSED])
                        ->latest('created_at')
                        ->first();

                    if (! $campaign) {
                        Notification::make()
                            ->title('No active SG6 campaign found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        app(SeedSendCampaignService::class)->cancelCampaign($campaign, auth()->user(), $data['reason'] ?? null);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('SG6 campaign cancelled.')
                        ->success()
                        ->send();
                }),
            Action::make('retry_seed_send_failed_subset')
                ->label('Retry SG6 Failed/Deferred')
                ->color('warning')
                ->visible(function (): bool {
                    if (! (bool) config('seed_send.enabled', false)) {
                        return false;
                    }

                    $campaign = $this->record->seedSendCampaigns()->latest('created_at')->first();
                    if (! $campaign) {
                        return false;
                    }

                    return $campaign->recipients()
                        ->whereIn('status', [\App\Models\SeedSendRecipient::STATUS_DEFERRED, \App\Models\SeedSendRecipient::STATUS_FAILED])
                        ->exists();
                })
                ->action(function (): void {
                    $campaign = $this->record->seedSendCampaigns()->latest('created_at')->first();

                    if (! $campaign) {
                        Notification::make()
                            ->title('No SG6 campaign found.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $affected = app(SeedSendCampaignService::class)->retryDeferredOrFailedRecipients($campaign, auth()->user(), 500);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($affected > 0 ? sprintf('Requeued %d SG6 recipients.', $affected) : 'No SG6 recipients to retry.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
