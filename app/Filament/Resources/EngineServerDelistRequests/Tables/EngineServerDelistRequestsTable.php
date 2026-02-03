<?php

namespace App\Filament\Resources\EngineServerDelistRequests\Tables;

use App\Models\EngineServerDelistRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EngineServerDelistRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                    ->color(fn (EngineServerDelistRequest $record): string => $record->status === 'resolved' ? 'success' : 'warning'),
                TextColumn::make('requestedBy.email')
                    ->label('Requested by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->since()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                    ]),
            ])
            ->emptyStateHeading('No delist requests')
            ->emptyStateDescription('Create a request when a server is listed on an RBL.');
    }
}
