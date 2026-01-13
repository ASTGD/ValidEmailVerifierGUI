<?php

namespace App\Filament\Resources\VerificationOrders\Pages;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationOrderStatus;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Filament\Resources\VerificationOrders\VerificationOrderResource;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use App\Services\OrderStorage;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class ViewVerificationOrder extends ViewRecord
{
    protected static string $resource = VerificationOrderResource::class;

    public function getTitle(): string | Htmlable
    {
        $record = $this->getRecord();
        $label = $record->order_number ?: ('#' . $record->id);

        return __('Order :number', ['number' => $label]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_job')
                ->label('View Job')
                ->url(function (): ?string {
                    $record = $this->getRecord();

                    if (! $record->job) {
                        return null;
                    }

                    return VerificationJobResource::getUrl('view', ['record' => $record->job]);
                })
                ->disabled(fn (): bool => ! $this->getRecord()->job),
            Action::make('activate')
                ->label('Activate')
                ->color('success')
                ->requiresConfirmation()
                ->disabled(function (): bool {
                    $record = $this->getRecord();

                    return $record->status !== VerificationOrderStatus::Pending
                        || (! $record->job && (! $record->input_disk || ! $record->input_key));
                })
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record->job) {
                        $record->job->update([
                            'status' => VerificationJobStatus::Pending,
                            'error_message' => null,
                            'failure_source' => null,
                            'failure_code' => null,
                            'started_at' => null,
                            'finished_at' => null,
                        ]);

                        $record->job->addLog('requeued', 'Job requeued by admin.', [
                            'order_id' => $record->id,
                        ], auth()->id());

                        $record->update([
                            'status' => VerificationOrderStatus::Processing,
                        ]);

                        AdminAuditLogger::log('order_requeued', $record, [
                            'verification_job_id' => $record->job->id,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Order requeued')
                            ->send();

                        return;
                    }

                    if (! $record->input_disk || ! $record->input_key) {
                        Notification::make()
                            ->danger()
                            ->title('Input file missing')
                            ->body('Upload an email list before activating this order.')
                            ->send();

                        return;
                    }

                    $jobStorage = app(JobStorage::class);
                    $orderStorage = app(OrderStorage::class);

                    $job = new VerificationJob([
                        'user_id' => $record->user_id,
                        'status' => VerificationJobStatus::Pending,
                        'original_filename' => $record->original_filename,
                    ]);
                    $job->id = (string) Str::uuid();
                    $job->input_disk = $jobStorage->disk();
                    $job->input_key = $jobStorage->inputKey($job);
                    $job->save();

                    $orderStorage->moveToJob($record, $job, $jobStorage);

                    $job->addLog('created', 'Job activated by admin.', [
                        'order_id' => $record->id,
                    ], auth()->id());

                    $record->update([
                        'verification_job_id' => $job->id,
                        'status' => VerificationOrderStatus::Processing,
                        'input_disk' => $job->input_disk,
                        'input_key' => $job->input_key,
                    ]);

                    AdminAuditLogger::log('order_activated', $record, [
                        'verification_job_id' => $job->id,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Order activated')
                        ->send();
                })
                ->tooltip(function (): ?string {
                    $record = $this->getRecord();

                    if ($record->status !== VerificationOrderStatus::Pending) {
                        return 'Only pending orders can be activated.';
                    }

                    if (! $record->job && (! $record->input_disk || ! $record->input_key)) {
                        return 'Upload an email list before activating.';
                    }

                    return null;
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->color('danger')
                ->requiresConfirmation()
                ->disabled(fn (): bool => ! in_array($this->getRecord()->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true))
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record->job && in_array($record->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)) {
                        $record->job->update([
                            'status' => VerificationJobStatus::Failed,
                            'error_message' => 'Order cancelled by admin.',
                            'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                            'failure_code' => 'cancelled',
                            'finished_at' => now(),
                        ]);

                        $record->job->addLog('cancelled', 'Order cancelled by admin.', [
                            'order_id' => $record->id,
                        ], auth()->id());
                    }

                    $record->update([
                        'status' => VerificationOrderStatus::Cancelled,
                    ]);

                    AdminAuditLogger::log('order_cancelled', $record);

                    Notification::make()
                        ->success()
                        ->title('Order cancelled')
                        ->send();
                })
                ->tooltip(function (): ?string {
                    if (! in_array($this->getRecord()->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)) {
                        return 'Only pending or processing orders can be cancelled.';
                    }

                    return null;
                }),
            Action::make('mark_fraud')
                ->label('Mark Fraud')
                ->color('danger')
                ->requiresConfirmation()
                ->disabled(fn (): bool => ! in_array($this->getRecord()->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true))
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record->job && in_array($record->job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)) {
                        $record->job->update([
                            'status' => VerificationJobStatus::Failed,
                            'error_message' => 'Order flagged as fraud by admin.',
                            'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                            'failure_code' => 'fraud',
                            'finished_at' => now(),
                        ]);

                        $record->job->addLog('fraud', 'Order flagged as fraud by admin.', [
                            'order_id' => $record->id,
                        ], auth()->id());
                    }

                    $record->update([
                        'status' => VerificationOrderStatus::Fraud,
                    ]);

                    AdminAuditLogger::log('order_marked_fraud', $record);

                    Notification::make()
                        ->success()
                        ->title('Order marked as fraud')
                        ->send();
                })
                ->tooltip(function (): ?string {
                    if (! in_array($this->getRecord()->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)) {
                        return 'Only pending or processing orders can be marked as fraud.';
                    }

                    return null;
                }),
            Action::make('back_to_pending')
                ->label('Back to Pending')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->getRecord()->status, [VerificationOrderStatus::Cancelled, VerificationOrderStatus::Fraud], true))
                ->action(function (): void {
                    $record = $this->getRecord();

                    if ($record->job) {
                        $record->job->update([
                            'status' => VerificationJobStatus::Pending,
                            'error_message' => null,
                            'failure_source' => null,
                            'failure_code' => null,
                            'started_at' => null,
                            'finished_at' => null,
                        ]);

                        $record->job->addLog('reopened', 'Order moved back to pending by admin.', [
                            'order_id' => $record->id,
                        ], auth()->id());
                    }

                    $record->update([
                        'status' => VerificationOrderStatus::Pending,
                    ]);

                    AdminAuditLogger::log('order_reopened', $record);

                    Notification::make()
                        ->success()
                        ->title('Order moved back to pending')
                        ->send();
                }),
        ];
    }
}
