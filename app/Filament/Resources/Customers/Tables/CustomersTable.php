<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('subscription_status')
                    ->label('Subscription')
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
                TextColumn::make('verification_jobs_count')
                    ->label('Jobs')
                    ->counts('verificationJobs')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable()
                    ->trueLabel('Verified')
                    ->falseLabel('Unverified'),
                TernaryFilter::make('has_subscription')
                    ->label('Subscription')
                    ->trueLabel('Has subscription')
                    ->falseLabel('No subscription')
                    ->queries(
                        true: fn ($query) => $query->whereHas('subscriptions'),
                        false: fn ($query) => $query->whereDoesntHave('subscriptions'),
                    ),
            ])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('Customer accounts will appear here once users sign up.');
    }
}
