<?php

namespace App\Filament\Resources\VerificationOrders\Schemas;

use App\Enums\VerificationOrderStatus;
use App\Models\VerificationOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Customer')
                            ->copyable(),
                        TextEntry::make('pricingPlan.name')
                            ->label('Pricing plan')
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Order')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(function ($state): string {
                                if ($state instanceof VerificationOrderStatus) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(function ($state): string {
                                $value = $state instanceof VerificationOrderStatus ? $state->value : (string) $state;

                                return match ($value) {
                                    VerificationOrderStatus::Pending->value => 'warning',
                                    VerificationOrderStatus::Processing->value => 'info',
                                    VerificationOrderStatus::Delivered->value => 'success',
                                    VerificationOrderStatus::Failed->value => 'danger',
                                    VerificationOrderStatus::Cancelled->value => 'gray',
                                    VerificationOrderStatus::Fraud->value => 'danger',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('email_count')
                            ->label('Email count')
                            ->numeric(),
                        TextEntry::make('amount_cents')
                            ->label('Amount')
                            ->formatStateUsing(function ($state, VerificationOrder $record): string {
                                $currency = strtoupper((string) ($record->currency ?: 'usd'));
                                $amount = $state !== null ? ((int) $state) / 100 : 0;

                                return sprintf('%s %.2f', $currency, $amount);
                            }),
                        TextEntry::make('original_filename')
                            ->label('Filename'),
                        TextEntry::make('input_key')
                            ->label('Input key')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Engine')
                    ->schema([
                        TextEntry::make('verification_job_id')
                            ->label('Verification job')
                            ->placeholder('-'),
                        TextEntry::make('checkout_intent_id')
                            ->label('Checkout intent')
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
