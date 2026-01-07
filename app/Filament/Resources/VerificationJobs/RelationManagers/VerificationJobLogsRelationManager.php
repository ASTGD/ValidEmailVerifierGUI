<?php

namespace App\Filament\Resources\VerificationJobs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerificationJobLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Logs';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Logged')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->toggleable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('context')
                    ->label('Context')
                    ->formatStateUsing(static function ($state): string {
                        if (empty($state)) {
                            return '-';
                        }

                        return json_encode($state, JSON_UNESCAPED_SLASHES);
                    })
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
