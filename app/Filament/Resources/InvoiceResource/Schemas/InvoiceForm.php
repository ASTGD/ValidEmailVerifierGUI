<?php

namespace App\Filament\Resources\InvoiceResource\Schemas;

use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make('Invoice Overview')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice #')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('user_id')
                                    ->relationship('user', 'email')
                                    ->label('Customer')
                                    ->searchable()
                                    ->required(),
                                DatePicker::make('date')
                                    ->label('Invoice Date')
                                    ->default(now())
                                    ->required(),
                                DatePicker::make('due_date')
                                    ->label('Due Date'),
                                Select::make('status')
                                    ->options([
                                        'Unpaid' => 'Unpaid',
                                        'Paid' => 'Paid',
                                        'Cancelled' => 'Cancelled',
                                        'Refunded' => 'Refunded',
                                        'Collections' => 'Collections',
                                    ])
                                    ->required(),
                                Select::make('currency')
                                    ->options([
                                        'USD' => 'USD',
                                        'EUR' => 'EUR',
                                        'GBP' => 'GBP',
                                        'BDT' => 'BDT',
                                    ])
                                    ->default('USD')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Totals & Notes')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->formatStateUsing(fn($state) => $state / 100)
                            ->dehydrateStateUsing(fn($state) => $state * 100),
                        TextInput::make('total')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->formatStateUsing(fn($state) => $state / 100)
                            ->dehydrateStateUsing(fn($state) => $state * 100),
                        DatePicker::make('paid_at')
                            ->label('Paid Date'),
                    ]),
                Section::make('Invoice Items')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->formatStateUsing(fn($state) => $state / 100)
                                    ->dehydrateStateUsing(fn($state) => $state * 100)
                                    ->columnSpan(1),
                                TextInput::make('type')
                                    ->default('manual')
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                    ]),
                Section::make('Additional Information')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Client Notes')
                            ->rows(3),
                    ]),
            ]);
    }
}
