<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Customer')
                            ->copyable(),
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('stripe_status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('stripe_price')
                            ->label('Price')
                            ->copyable(),
                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->numeric(),
                        TextEntry::make('trial_ends_at')
                            ->label('Trial ends')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('ends_at')
                            ->label('Ends at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
