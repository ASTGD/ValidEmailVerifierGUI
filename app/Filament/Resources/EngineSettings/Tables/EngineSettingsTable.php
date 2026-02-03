<?php

namespace App\Filament\Resources\EngineSettings\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EngineSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                IconColumn::make('engine_paused')
                    ->label('Engine paused')
                    ->boolean(),
                IconColumn::make('enhanced_mode_enabled')
                    ->label('Enhanced enabled')
                    ->boolean(),
                TextColumn::make('role_accounts_behavior')
                    ->label('Role behavior')
                    ->badge(),
                TextColumn::make('role_accounts_list')
                    ->label('Role list')
                    ->limit(40),
                TextColumn::make('catch_all_policy')
                    ->label('Catch-all policy')
                    ->badge(),
                TextColumn::make('catch_all_promote_threshold')
                    ->label('Catch-all threshold')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : $state),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
