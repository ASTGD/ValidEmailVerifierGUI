<?php

namespace App\Filament\Resources\AdminAuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Admin')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state): string => $state ? class_basename((string) $state) : '-')
                    ->toggleable(),
                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('metadata')
                    ->label('Metadata')
                    ->formatStateUsing(static function ($state): string {
                        if (empty($state)) {
                            return '-';
                        }

                        return json_encode($state, JSON_UNESCAPED_SLASHES);
                    })
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('No audit logs yet')
            ->emptyStateDescription('Admin actions will appear here.')
            ->recordActions([])
            ->bulkActions([]);
    }
}
