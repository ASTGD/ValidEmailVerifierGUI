<?php

namespace App\Filament\Resources\VerifierDomains\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerifierDomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('domain')
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
