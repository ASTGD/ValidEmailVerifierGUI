<?php

namespace App\Filament\Resources\EngineServers\Tables;

use App\Models\EngineServer;
use App\Support\AdminAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
                TextColumn::make('helo_name')
                    ->label('Host Name')
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('mail_from_address')
                    ->label('MAIL FROM')
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('verifierDomain.domain')
                    ->label('Verifier domain')
                    ->toggleable()
                    ->placeholder('-')
                    ->getStateUsing(fn (EngineServer $record): string => $record->verifierDomain?->domain
                        ?? (string) $record->identity_domain),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (EngineServer $record): string => $record->isOnline() ? 'Online' : 'Offline')
                    ->color(fn (EngineServer $record): string => $record->isOnline() ? 'success' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Enabled')
                    ->boolean(),
                IconColumn::make('drain_mode')
                    ->label('Drain')
                    ->boolean(),
                TextColumn::make('max_concurrency')
                    ->label('Max concurrency')
                    ->numeric()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('processed_chunks')
                    ->label('Processed')
                    ->numeric()
                    ->getStateUsing(fn (EngineServer $record): int => $record->chunks()
                        ->where('status', 'completed')
                        ->count())
                    ->toggleable(),
                TextColumn::make('last_heartbeat_at')
                    ->label('Last heartbeat')
                    ->since()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Enabled'),
                TernaryFilter::make('drain_mode')
                    ->label('Drain mode'),
            ])
            ->emptyStateHeading('No engine servers')
            ->emptyStateDescription('Register engine servers to track heartbeat status.')
            ->recordActions([
                Action::make('record_heartbeat')
                    ->label('Record heartbeat')
                    ->action(function (EngineServer $record): void {
                        $record->update(['last_heartbeat_at' => now()]);

                        AdminAuditLogger::log('engine_heartbeat_recorded', $record);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (EngineServer $record): bool => $record->is_active),
                EditAction::make(),
                Action::make('delete_server')
                    ->label('Delete')
                    ->color('danger')
                    ->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->action(function (EngineServer $record): void {
                        AdminAuditLogger::log('engine_server_deleted', $record, [
                            'name' => $record->name,
                            'ip_address' => $record->ip_address,
                        ]);

                        $record->delete();
                    }),
            ]);
    }
}
