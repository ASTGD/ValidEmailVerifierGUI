<?php

namespace App\Filament\Resources\VerificationJobs\Pages;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Jobs\FinalizeVerificationJob;
use App\Models\VerificationJob;
use App\Support\AdminAuditLogger;
use App\Support\EngineSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewVerificationJob extends ViewRecord
{
    protected static string $resource = VerificationJobResource::class;

    /**
     * @return array<class-string<Action>>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('change_mode')
                ->label('Change Mode')
                ->form([
                    Select::make('verification_mode')
                        ->label('Verification mode')
                        ->helperText('Enhanced is gated and currently behaves like Standard until enabled.')
                        ->options(function (): array {
                            $options = [];

                            foreach (VerificationMode::cases() as $mode) {
                                $options[$mode->value] = $mode->label();
                            }

                            return $options;
                        })
                        ->required(),
                ])
                ->fillForm(function (VerificationJob $record): array {
                    return [
                        'verification_mode' => $record->verification_mode?->value ?? VerificationMode::Standard->value,
                    ];
                })
                ->action(function (array $data): void {
                    if (! EngineSettings::enhancedModeEnabled()
                        && $data['verification_mode'] === VerificationMode::Enhanced->value) {
                        Notification::make()
                            ->title('Enhanced mode is coming soon.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $record = $this->record;
                    $from = $record->verification_mode?->value ?? VerificationMode::Standard->value;

                    $record->update([
                        'verification_mode' => $data['verification_mode'],
                    ]);

                    $record->addLog('verification_mode_changed', 'Verification mode updated by admin.', [
                        'from' => $from,
                        'to' => $data['verification_mode'],
                        'actor_id' => auth()->id(),
                    ], auth()->id());

                    AdminAuditLogger::log('job_mode_updated', $record, [
                        'from' => $from,
                        'to' => $data['verification_mode'],
                    ]);

                    Notification::make()
                        ->title('Verification mode updated.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->disabled(fn (): bool => ! EngineSettings::enhancedModeEnabled())
                ->tooltip(fn (): ?string => EngineSettings::enhancedModeEnabled() ? null : 'Coming soon'),
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
        ];
    }
}
