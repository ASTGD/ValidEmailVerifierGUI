<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'active' => 'success',
                            'trialing' => 'warning',
                            'past_due', 'unpaid' => 'danger',
                            'canceled', 'incomplete', 'incomplete_expired' => 'gray',
                            default => 'gray',
                        };
                    }),
                TextColumn::make('stripe_price')
                    ->label('Price')
                    ->toggleable(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('trial_ends_at')
                    ->label('Trial ends')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')
                    ->label('Ends at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past due',
                        'unpaid' => 'Unpaid',
                        'canceled' => 'Canceled',
                        'incomplete' => 'Incomplete',
                        'incomplete_expired' => 'Incomplete expired',
                    ]),
            ]);
    }
}
