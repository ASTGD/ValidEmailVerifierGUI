<?php

namespace App\Filament\Resources\VerificationJobChunks\Schemas;

use App\Filament\Resources\VerificationJobs\VerificationJobResource;
use App\Models\VerificationJobChunk;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationJobChunkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Chunk Details')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Chunk ID')
                            ->copyable(),
                        TextEntry::make('verification_job_id')
                            ->label('Job ID')
                            ->copyable()
                            ->url(function (VerificationJobChunk $record): ?string {
                                if (! $record->job) {
                                    return null;
                                }

                                return VerificationJobResource::getUrl('view', ['record' => $record->job]);
                            }),
                        TextEntry::make('chunk_no')
                            ->label('Chunk #'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(function (string $state): string {
                                return match ($state) {
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('attempts')
                            ->label('Attempts'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Engine')
                    ->schema([
                        TextEntry::make('engineServer.name')
                            ->label('Engine server')
                            ->formatStateUsing(function ($state, VerificationJobChunk $record): string {
                                if (! $record->engineServer) {
                                    return '-';
                                }

                                return sprintf('%s (%s)', $record->engineServer->name, $record->engineServer->ip_address);
                            }),
                        TextEntry::make('assigned_worker_id')
                            ->label('Worker')
                            ->placeholder('-'),
                        TextEntry::make('claimed_at')
                            ->label('Claimed at')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('claim_expires_at')
                            ->label('Lease expires')
                            ->since()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Files')
                    ->schema([
                        TextEntry::make('input_disk')
                            ->label('Input disk')
                            ->placeholder('-'),
                        TextEntry::make('input_key')
                            ->label('Input key')
                            ->copyable(),
                        TextEntry::make('output_disk')
                            ->label('Output disk')
                            ->placeholder('-'),
                        TextEntry::make('valid_key')
                            ->label('Valid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('invalid_key')
                            ->label('Invalid key')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('risky_key')
                            ->label('Risky key')
                            ->placeholder('-')
                            ->copyable(),
                    ])
                    ->columns(2),
                Section::make('Counts')
                    ->schema([
                        TextEntry::make('email_count')
                            ->label('Emails')
                            ->numeric(),
                        TextEntry::make('valid_count')
                            ->label('Valid')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('invalid_count')
                            ->label('Invalid')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('risky_count')
                            ->label('Risky')
                            ->numeric()
                            ->placeholder('-'),
                    ])
                    ->columns(4),
                Section::make('Activity')
                    ->schema([
                        RepeatableEntry::make('activity')
                            ->label('Recent Logs')
                            ->state(function (VerificationJobChunk $record): array {
                                $logs = $record->job?->logs()
                                    ->where('context->chunk_id', (string) $record->id)
                                    ->latest()
                                    ->limit(20)
                                    ->get();

                                if (! $logs) {
                                    return [];
                                }

                                return $logs->map(function ($log): array {
                                    return [
                                        'event' => $log->event,
                                        'message' => $log->message,
                                        'actor' => $log->user?->email ?: __('System'),
                                        'created_at' => $log->created_at,
                                    ];
                                })->all();
                            })
                            ->schema([
                                TextEntry::make('event')
                                    ->label('Event')
                                    ->badge(),
                                TextEntry::make('message')
                                    ->label('Message')
                                    ->placeholder('-'),
                                TextEntry::make('actor')
                                    ->label('Actor')
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label('Time')
                                    ->since(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
