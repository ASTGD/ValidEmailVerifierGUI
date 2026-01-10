<?php

namespace App\Filament\Resources\EngineServers\Tables;

use App\Models\EngineServer;
use Filament\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EngineServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('environment')
                    ->label('Environment')
                    ->toggleable(),
                TextColumn::make('region')
                    ->label('Region')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (EngineServer $record): string => $record->isOnline() ? 'Online' : 'Offline')
                    ->color(fn (EngineServer $record): string => $record->isOnline() ? 'success' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('last_heartbeat_at')
                    ->label('Last heartbeat')
                    ->since()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Action::make('record_heartbeat')
                    ->label('Record heartbeat')
                    ->action(fn (EngineServer $record) => $record->update(['last_heartbeat_at' => now()]))
                    ->requiresConfirmation()
                    ->visible(fn (EngineServer $record): bool => $record->is_active),
                EditAction::make(),
            ]);
    }
}
