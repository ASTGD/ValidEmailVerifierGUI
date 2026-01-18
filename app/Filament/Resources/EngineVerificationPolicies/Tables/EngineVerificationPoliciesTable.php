<?php

namespace App\Filament\Resources\EngineVerificationPolicies\Tables;

use App\Models\EngineVerificationPolicy;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EngineVerificationPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('mode')
            ->columns([
                TextColumn::make('mode')
                    ->label('Mode')
                    ->formatStateUsing(fn ($state): string => ucfirst((string) $state)),
                IconColumn::make('enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('dns_timeout_ms')
                    ->label('DNS timeout')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('smtp_connect_timeout_ms')
                    ->label('SMTP connect')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('smtp_read_timeout_ms')
                    ->label('SMTP read')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('max_mx_attempts')
                    ->label('Max MX')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('max_concurrency_default')
                    ->label('Max concurrency')
                    ->numeric(),
                TextColumn::make('per_domain_concurrency')
                    ->label('Per-domain')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('global_connects_per_minute')
                    ->label('Global connects/min')
                    ->numeric()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('tempfail_backoff_seconds')
                    ->label('Backoff (s)')
                    ->numeric()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('circuit_breaker_tempfail_rate')
                    ->label('Circuit breaker rate')
                    ->numeric()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->emptyStateHeading('No verification policies')
            ->emptyStateDescription('Default policies are created during migration.')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }
}
