<?php

namespace App\Filament\Resources\EngineServerBlacklistEvents\Tables;

use App\Models\EngineServerBlacklistEvent;
use App\Models\EngineServerDelistRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EngineServerBlacklistEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen', 'desc')
            ->columns([
                TextColumn::make('engineServer.name')
                    ->label('Server')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('engineServer.ip_address')
                    ->label('IP')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('rbl')
                    ->label('RBL')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (EngineServerBlacklistEvent $record): string => $record->status === 'active' ? 'danger' : 'success'),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (EngineServerBlacklistEvent $record): string => match ($record->severity) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('listed_count')
                    ->label('Listed count')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('first_seen')
                    ->label('First seen')
                    ->since()
                    ->toggleable(),
                TextColumn::make('last_seen')
                    ->label('Last seen')
                    ->since(),
                TextColumn::make('last_response')
                    ->label('Last response')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'resolved' => 'Resolved',
                    ]),
                SelectFilter::make('severity')
                    ->options([
                        'critical' => 'Critical',
                        'warning' => 'Warning',
                        'info' => 'Info',
                    ]),
            ])
            ->emptyStateHeading('No blacklist events')
            ->emptyStateDescription('The monitor will surface blacklist listings when detected.')
            ->recordActions([
                Action::make('delist_request')
                    ->label('Request delist')
                    ->icon('heroicon-m-inbox-arrow-down')
                    ->form([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Optional context for the delist request.'),
                    ])
                    ->action(function (EngineServerBlacklistEvent $record, array $data): void {
                        EngineServerDelistRequest::query()->create([
                            'engine_server_id' => $record->engine_server_id,
                            'rbl' => $record->rbl,
                            'status' => 'open',
                            'notes' => $data['notes'] ?? null,
                            'requested_by' => auth()->id(),
                        ]);
                    }),
            ]);
    }
}
