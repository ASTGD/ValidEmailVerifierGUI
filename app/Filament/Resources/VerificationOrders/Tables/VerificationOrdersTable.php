<?php

namespace App\Filament\Resources\VerificationOrders\Tables;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationOrderStatus;
use App\Models\VerificationJob;
use App\Models\VerificationOrder;
use App\Services\JobStorage;
use App\Services\OrderStorage;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VerificationOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof VerificationOrderStatus) {
                            return $state->label();
                        }

                        return ucfirst((string) $state);
                    })
                    ->color(function ($state): string {
                        $value = $state instanceof VerificationOrderStatus ? $state->value : (string) $state;

                        return match ($value) {
                            VerificationOrderStatus::Pending->value => 'warning',
                            VerificationOrderStatus::Processing->value => 'info',
                            VerificationOrderStatus::Delivered->value => 'success',
                            VerificationOrderStatus::Failed->value => 'danger',
                            VerificationOrderStatus::Cancelled->value => 'gray',
                            VerificationOrderStatus::Fraud->value => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('email_count')
                    ->label('Emails')
                    ->numeric(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, VerificationOrder $record): string {
                        $currency = strtoupper((string) ($record->currency ?: 'usd'));
                        $amount = $state !== null ? ((int) $state) / 100 : 0;

                        return sprintf('%s %.2f', $currency, $amount);
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('verification_job_id')
                    ->label('Job ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('input_key')
                    ->label('Input key')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders will appear here once customers complete checkout.')
            ->recordActions([
                ViewAction::make(),
                Action::make('activate')
                    ->label('Activate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(function (VerificationOrder $record): bool {
                        return $record->verification_job_id
                            || ! $record->input_disk
                            || ! $record->input_key;
                    })
                    ->tooltip(function (VerificationOrder $record): ?string {
                        if ($record->verification_job_id) {
                            return 'Order already activated.';
                        }

                        if (! $record->input_disk || ! $record->input_key) {
                            return 'Upload an email list before activating.';
                        }

                        return null;
                    })
                    ->action(function (VerificationOrder $record): void {
                        if ($record->verification_job_id) {
                            Notification::make()
                                ->warning()
                                ->title('Order already activated')
                                ->body('This order is already linked to a verification job.')
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
                    })
                    ->visible(fn (VerificationOrder $record): bool => $record->status === VerificationOrderStatus::Pending),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VerificationOrder $record): void {
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
                    })
                    ->visible(fn (VerificationOrder $record): bool => in_array($record->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)),
                Action::make('mark_fraud')
                    ->label('Mark Fraud')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VerificationOrder $record): void {
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
                    })
                    ->visible(fn (VerificationOrder $record): bool => in_array($record->status, [VerificationOrderStatus::Pending, VerificationOrderStatus::Processing], true)),
            ]);
    }

    private static function statusOptions(): array
    {
        $options = [];

        foreach (VerificationOrderStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
