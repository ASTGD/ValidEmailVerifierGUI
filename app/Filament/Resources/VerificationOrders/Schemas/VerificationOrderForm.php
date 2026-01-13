<?php

namespace App\Filament\Resources\VerificationOrders\Schemas;

use App\Support\Roles;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'email', modifyQueryUsing: fn ($query) => $query->role(Roles::CUSTOMER))
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(1),
                Section::make('Order')
                    ->schema([
                        FileUpload::make('input_file')
                            ->label('Email list')
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(((int) config('verifier.checkout_upload_max_mb', 10)) * 1024)
                            ->storeFiles(false)
                            ->required(),
                        TextInput::make('email_count')
                            ->label('Email count')
                            ->numeric()
                            ->required(),
                        TextInput::make('amount_cents')
                            ->label('Amount (cents)')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->label('Currency')
                            ->default((string) config('cashier.currency', 'usd'))
                            ->maxLength(3)
                            ->required(),
                        Select::make('pricing_plan_id')
                            ->label('Pricing plan')
                            ->relationship('pricingPlan', 'name')
                            ->searchable()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
