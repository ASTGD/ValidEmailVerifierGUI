<?php

namespace App\Filament\Resources\EngineServerReputationChecks\Tables;

use App\Models\EngineServerReputationCheck;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EngineServerReputationChecksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('checked_at', 'desc')
            ->columns([
                TextColumn::make('engineServer.name')
                    ->label('Server')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('rbl')
                    ->label('RBL')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (EngineServerReputationCheck $record): string => match ($record->status) {
                        'listed' => 'danger',
                        'clear' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('response')
                    ->label('Response')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('checked_at')
                    ->label('Checked')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'listed' => 'Listed',
                        'clear' => 'Clear',
                        'error' => 'Error',
                    ]),
            ])
            ->emptyStateHeading('No reputation checks')
            ->emptyStateDescription('The monitor will log checks for active servers.');
    }
}
