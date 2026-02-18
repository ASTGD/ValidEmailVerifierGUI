<?php

namespace App\Filament\Resources\VerificationJobChunks\Tables;

use App\Filament\Resources\EngineServers\EngineServerResource;
use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\EngineServer;
use App\Models\VerificationJobChunk;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VerificationJobChunksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('verification_job_id')
                    ->label('Job ID')
                    ->copyable()
                    ->url(function (VerificationJobChunk $record): ?string {
                        if (! $record->job) {
                            return null;
                        }

                        return VerificationJobResource::getUrl('view', ['record' => $record->job]);
                    })
                    ->searchable(),
                TextColumn::make('chunk_no')
                    ->label('Chunk #')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'pending' => 'warning',
                            'processing' => 'info',
                            'completed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->sortable(),
                TextColumn::make('processing_stage')
                    ->label('Stage')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : 'SCREENING')
                    ->color(fn (?string $state): string => $state === 'smtp_probe' ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('email_count')
                    ->label('Email Count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('engineServer.name')
                    ->label('Engine Server')
                    ->formatStateUsing(function ($state, VerificationJobChunk $record): string {
                        if (! $record->engineServer) {
                            return '-';
                        }

                        return sprintf('%s (%s)', $record->engineServer->name, $record->engineServer->ip_address);
                    })
                    ->url(fn (VerificationJobChunk $record): ?string => $record->engineServer ? EngineServerResource::getUrl('edit', ['record' => $record->engineServer]) : null),
                TextColumn::make('assigned_worker_id')
                    ->label('Worker')
                    ->placeholder('-'),
                TextColumn::make('attempts')
                    ->label('Attempts')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('claim_expires_at')
                    ->label('Lease Expires')
                    ->since()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('engine_server_id')
                    ->label('Engine Server')
                    ->options(fn () => EngineServer::query()->pluck('name', 'id')->all()),
                Filter::make('leased')
                    ->label('Leased only')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('claim_expires_at')
                        ->where('claim_expires_at', '>', now())),
                Filter::make('lease_expired')
                    ->label('Lease expired')
                    ->query(fn (Builder $query) => $query
                        ->where('status', 'processing')
                        ->whereNotNull('claim_expires_at')
                        ->where('claim_expires_at', '<', now())),
                Filter::make('job_id')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('job_id')
                            ->label('Job ID')
                            ->placeholder('Enter job UUID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $jobId = $data['job_id'] ?? null;

                        if (! $jobId) {
                            return $query;
                        }

                        return $query->where('verification_job_id', 'like', '%'.$jobId.'%');
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Logs'),
                Action::make('requeue')
                    ->label('Requeue')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->disabled(fn (VerificationJobChunk $record): bool => $record->status === 'completed')
                    ->action(function (VerificationJobChunk $record): void {
                        $record->update([
                            'status' => 'pending',
                            'attempts' => $record->attempts + 1,
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

                        $record->job?->addLog('chunk_requeued', 'Chunk requeued by admin.', [
                            'chunk_id' => (string) $record->id,
                            'chunk_no' => $record->chunk_no,
                            'attempts' => $record->attempts,
                        ], auth()->id());

                        Notification::make()
                            ->title('Chunk requeued.')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->disabled(fn (VerificationJobChunk $record): bool => $record->status === 'completed')
                    ->action(function (VerificationJobChunk $record): void {
                        $record->update([
                            'status' => 'failed',
                            'attempts' => $record->attempts + 1,
                            'claimed_at' => null,
                            'claim_expires_at' => null,
                            'claim_token' => null,
                            'engine_server_id' => null,
                            'assigned_worker_id' => null,
                        ]);

                        $record->job?->addLog('chunk_marked_failed', 'Chunk marked failed by admin.', [
                            'chunk_id' => (string) $record->id,
                            'chunk_no' => $record->chunk_no,
                            'attempts' => $record->attempts,
                        ], auth()->id());

                        Notification::make()
                            ->title('Chunk marked failed.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
