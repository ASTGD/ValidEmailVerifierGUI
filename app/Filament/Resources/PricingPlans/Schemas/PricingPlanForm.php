<?php

namespace App\Filament\Resources\PricingPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique('pricing_plans', 'slug', ignoreRecord: true),
                        TextInput::make('stripe_price_id')
                            ->label('Stripe price ID')
                            ->maxLength(255),
                        Select::make('billing_interval')
                            ->label('Billing interval')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                            ])
                            ->native(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price_per_email')
                            ->label('Price per email')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.0001'),
                        TextInput::make('price_per_1000')
                            ->label('Price per 1,000')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01'),
                        TextInput::make('credits_per_month')
                            ->label('Credits per month')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(3),
                Section::make('Limits')
                    ->schema([
                        TextInput::make('max_file_size_mb')
                            ->label('Max file size (MB)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('concurrency_limit')
                            ->label('Concurrency limit')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
