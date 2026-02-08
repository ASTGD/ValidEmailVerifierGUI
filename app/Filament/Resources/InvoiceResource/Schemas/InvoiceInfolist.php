<?php

namespace App\Filament\Resources\InvoiceResource\Schemas;

use App\Models\Invoice;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                // Large Invoice Number at the very top
                Placeholder::make('page_header_invoice')
                    ->hiddenLabel()
                    ->color('info')
                    ->content(fn($record) => $record ? new HtmlString('
                            <p style="font-size: 20px; font-weight: bold;">
                                Invoice Number # ' . $record->invoice_number . '
                            </p>
                    ') : null),
                Section::make('Invoice Overview')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.email')->label('Customer'),
                                TextEntry::make('date')->date()->label('Invoice Date'),
                                TextEntry::make('due_date')->date()->label('Due Date'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'Paid' => 'success',
                                        'Unpaid' => 'warning',
                                        'Cancelled' => 'danger',
                                        'Refunded' => 'info',
                                        'Collections' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('currency')->label('Currency'),
                                TextEntry::make('total')->label('Total Amount')
                                    ->formatStateUsing(fn($state) => number_format($state / 100, 2))
                                    ->weight('bold')
                                    ->color('info')
                            ]),
                    ]),
                // Section::make('Totals')
                //     ->columnSpan(1)
                //     ->schema([
                //         TextEntry::make('subtotal')
                //             ->formatStateUsing(fn($state) => number_format($state / 100, 2)),
                //         TextEntry::make('total')
                //             ->formatStateUsing(fn($state) => number_format($state / 100, 2))
                //             ->weight('bold')
                //             ->size('lg'),
                //         TextEntry::make('paid_at')->date()->label('Paid At')->placeholder('Not paid'),
                //     ]),
                Section::make('Invoice Items')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('description')->columnSpan(2),
                                TextEntry::make('amount')
                                    ->formatStateUsing(fn($state) => number_format($state / 100, 2))
                                    ->columnSpan(1),
                                // TextEntry::make('type')->columnSpan(1),
                            ])
                            ->columns(4)
                    ]),
                Section::make('Notes')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('notes')->placeholder('No notes'),
                    ]),
            ]);
    }
}
