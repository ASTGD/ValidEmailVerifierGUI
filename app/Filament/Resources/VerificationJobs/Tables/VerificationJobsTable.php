<?php

namespace App\Filament\Resources\VerificationJobs\Tables;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Models\EngineServer;
use App\Models\VerificationJob;
use App\Support\AdminAuditLogger;
use App\Support\JobProgressCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                ViewColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (VerificationJob $record): int => JobProgressCalculator::progressPercent($record))
                    ->view('filament.columns.progress-bar')
                    ->extraCellAttributes(['class' => 'w-32']),
                TextColumn::make('phase')
                    ->label('Phase')
                    ->getStateUsing(fn (VerificationJob $record): string => JobProgressCalculator::phaseLabel($record))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('processed')
                    ->label('Processed')
                    ->getStateUsing(function (VerificationJob $record): string {
                        $processed = $record->metrics?->processed_emails ?? 0;
                        $total = $record->metrics?->total_emails ?? $record->total_emails ?? 0;

                        return $total > 0 ? "{$processed} / {$total}" : (string) $processed;
                    })
                    ->toggleable(),
                TextColumn::make('cache_hits')
                    ->label('Cache Hits')
                    ->getStateUsing(fn (VerificationJob $record): int => (int) ($record->metrics?->cache_hit_count ?? 0))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cache_misses')
                    ->label('Cache Misses')
                    ->getStateUsing(fn (VerificationJob $record): int => (int) ($record->metrics?->cache_miss_count ?? 0))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verification_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof VerificationMode) {
                            return $state->label();
                        }

                        return ucfirst((string) $state);
                    })
                    ->color(fn (): string => 'gray')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('engine_server')
                    ->label('Engine Server')
                    ->getStateUsing(function (VerificationJob $record): string {
                        if (! $record->engineServer) {
                            return '-';
                        }

                        return sprintf('%s (%s)', $record->engineServer->name, $record->engineServer->ip_address);
                    })
                    ->toggleable(),
                TextColumn::make('claimed_at')
                    ->label('Claimed')
                    ->since()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('claim_expires_at')
                    ->label('Lease expires')
                    ->since()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('input_key')
                    ->label('Input Key')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('output_key')
                    ->label('Output Key')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->poll('10s')
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
                TernaryFilter::make('claimed')
                    ->label('Claimed')
                    ->trueLabel('Claimed')
                    ->falseLabel('Unclaimed')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('claimed_at'),
                        false: fn ($query) => $query->whereNull('claimed_at')
                    ),
                SelectFilter::make('engine_server_id')
                    ->label('Engine server')
                    ->relationship('engineServer', 'name')
                    ->getOptionLabelFromRecordUsing(function (EngineServer $record): string {
                        return sprintf('%s (%s)', $record->name, $record->ip_address);
                    })
                    ->searchable(),
                Filter::make('failed_chunks')
                    ->label('Failed chunks')
                    ->query(fn (Builder $query) => $query
                        ->whereHas('chunks', fn ($chunkQuery) => $chunkQuery->where('status', 'failed'))),
                Filter::make('missing_outputs')
                    ->label('Missing outputs')
                    ->query(fn (Builder $query) => $query
                        ->where('status', VerificationJobStatus::Completed)
                        ->where(function ($inner) {
                            $inner->whereNull('valid_key')
                                ->orWhereNull('invalid_key')
                                ->orWhereNull('risky_key');
                        })),
                Filter::make('ready_for_finalization')
                    ->label('Ready for finalization')
                    ->query(fn (Builder $query) => $query
                        ->where('status', VerificationJobStatus::Processing)
                        ->whereHas('chunks')
                        ->whereDoesntHave('chunks', fn ($chunkQuery) => $chunkQuery->where('status', '!=', 'completed'))
                        ->where(function ($inner) {
                            $inner->whereNull('valid_key')
                                ->orWhereNull('invalid_key')
                                ->orWhereNull('risky_key');
                        })),
            ])
            ->emptyStateHeading('No verification jobs yet')
            ->emptyStateDescription('Jobs will appear here once customers upload lists.')
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
                            'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                            'failure_code' => 'manual_fail',
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

                        app(\App\Services\JobMetricsRecorder::class)->recordPhase($record, 'failed', [
                            'progress_percent' => 100,
                        ]);

                        AdminAuditLogger::log('job_mark_failed', $record, [
                            'error_message' => $data['error_message'],
                        ]);
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
                            'failure_source' => VerificationJob::FAILURE_SOURCE_ADMIN,
                            'failure_code' => 'cancelled',
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

                        app(\App\Services\JobMetricsRecorder::class)->recordPhase($record, 'failed', [
                            'progress_percent' => 100,
                        ]);

                        AdminAuditLogger::log('job_cancelled', $record, [
                            'from' => $fromStatus->value,
                            'to' => VerificationJobStatus::Failed->value,
                        ]);
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
                            'failure_source' => null,
                            'failure_code' => null,
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

                        AdminAuditLogger::log('job_retried', $record, [
                            'from' => $fromStatus->value,
                            'to' => VerificationJobStatus::Pending->value,
                        ]);
                    })
                    ->visible(fn (VerificationJob $record): bool => $record->status === VerificationJobStatus::Failed && $record->failure_source !== VerificationJob::FAILURE_SOURCE_ADMIN),
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
