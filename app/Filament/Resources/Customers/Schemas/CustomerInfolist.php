<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        TextEntry::make('email_verified_at')
                            ->label('Email verified')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Subscription')
                    ->schema([
                        TextEntry::make('subscription_status')
                            ->label('Status')
                            ->badge()
                            ->getStateUsing(function (User $record): string {
                                $subscription = $record->subscription('default');

                                if (! $subscription) {
                                    return 'None';
                                }

                                if ($subscription->onGracePeriod()) {
                                    return 'Grace period';
                                }

                                return $record->subscribed('default') ? 'Active' : 'Inactive';
                            })
                            ->color(function (string $state): string {
                                return match ($state) {
                                    'Active' => 'success',
                                    'Grace period' => 'warning',
                                    'Inactive' => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('stripe_id')
                            ->label('Stripe customer')
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('verification_jobs_count')
                            ->label('Total jobs')
                            ->getStateUsing(fn (User $record): int => $record->verificationJobs()->count()),
                    ])
                    ->columns(3),
            ]);
    }
}
