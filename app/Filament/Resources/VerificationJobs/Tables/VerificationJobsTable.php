<?php

namespace App\Filament\Resources\VerificationJobs\Tables;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VerificationJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Job ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof VerificationJobStatus) {
                            return $state->label();
                        }

                        return ucfirst((string) $state);
                    })
                    ->color(function ($state): string {
                        $value = $state instanceof VerificationJobStatus ? $state->value : (string) $state;

                        return match ($value) {
                            VerificationJobStatus::Pending->value => 'warning',
                            VerificationJobStatus::Processing->value => 'info',
                            VerificationJobStatus::Completed->value => 'success',
                            VerificationJobStatus::Failed->value => 'danger',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('input_key')
                    ->label('Input Key')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('output_key')
                    ->label('Output Key')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('error_message')
                            ->label('Error message')
                            ->required(),
                    ])
                    ->action(function (VerificationJob $record, array $data): void {
                        $record->update([
                            'status' => VerificationJobStatus::Failed,
                            'error_message' => $data['error_message'],
                            'finished_at' => now(),
                        ]);

                        $record->addLog(
                            'failed',
                            'Job marked failed by admin.',
                            [
                                'error_message' => $data['error_message'],
                            ],
                            auth()->id()
                        );
                    })
                    ->visible(fn (VerificationJob $record): bool => $record->status !== VerificationJobStatus::Failed),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VerificationJob $record): void {
                        $fromStatus = $record->status;

                        $record->update([
                            'status' => VerificationJobStatus::Failed,
                            'error_message' => 'Cancelled by admin.',
                            'finished_at' => now(),
                        ]);

                        $record->addLog(
                            'cancelled',
                            'Job cancelled by admin.',
                            [
                                'from' => $fromStatus->value,
                                'to' => VerificationJobStatus::Failed->value,
                            ],
                            auth()->id()
                        );
                    })
                    ->visible(fn (VerificationJob $record): bool => in_array($record->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)),
                Action::make('retry')
                    ->label('Retry')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (VerificationJob $record): void {
                        $fromStatus = $record->status;

                        $record->update([
                            'status' => VerificationJobStatus::Pending,
                            'error_message' => null,
                            'started_at' => null,
                            'finished_at' => null,
                            'output_disk' => null,
                            'output_key' => null,
                            'total_emails' => null,
                            'valid_count' => null,
                            'invalid_count' => null,
                            'risky_count' => null,
                            'unknown_count' => null,
                        ]);

                        $record->addLog(
                            'retried',
                            'Job re-queued by admin.',
                            [
                                'from' => $fromStatus->value,
                                'to' => VerificationJobStatus::Pending->value,
                            ],
                            auth()->id()
                        );
                    })
                    ->visible(fn (VerificationJob $record): bool => $record->status === VerificationJobStatus::Failed),
            ]);
    }

    private static function statusOptions(): array
    {
        $options = [];

        foreach (VerificationJobStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
