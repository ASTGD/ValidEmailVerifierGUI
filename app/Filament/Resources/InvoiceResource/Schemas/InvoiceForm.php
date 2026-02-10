<?php

namespace App\Filament\Resources\InvoiceResource\Schemas;

use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
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

                Tabs::make('Invoice Management')
                    ->tabs([
                        Tab::make('Summary')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema([
                                        Section::make('Invoice Overview')
                                            ->columnSpan(2)
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        Placeholder::make('user_display')
                                                            ->label('Customer')
                                                            ->content(fn($record) => $record?->user?->name),
                                                        Placeholder::make('status_display')
                                                            ->label('Status')
                                                            ->content(fn($record) => $record ? new HtmlString('
                                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest" style="background-color: ' . match ($record->status) {
                                                                'Paid' => '#dcfce7; color: #166534;',
                                                                'Unpaid' => '#fee2e2; color: #991b1b;',
                                                                'Cancelled' => '#f1f5f9; color: #475569;',
                                                                'Refunded' => '#dbeafe; color: #1e40af;',
                                                                default => '#f1f5f9; color: #475569;',
                                                            } . '">
                                                                    ' . $record->status . '
                                                                </span>
                                                            ') : '-'),
                                                        Placeholder::make('date_display')
                                                            ->label('Invoice Date')
                                                            ->content(fn($record) => $record?->date?->format('M d, Y')),
                                                        Placeholder::make('due_date_display')
                                                            ->label('Due Date')
                                                            ->content(fn($record) => $record?->due_date?->format('M d, Y')),
                                                    ]),
                                            ]),
                                        Section::make('Financials')
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('live_total_amount')
                                                    ->label('Total Amount')
                                                    ->content(function (Get $get, $record) {
                                                        $items = $get('items') ?? [];
                                                        $total = collect($items)->sum(fn($item) => (float) ($item['amount'] ?? 0));
                                                        $currency = $get('currency') ?? $record?->currency ?? 'USD';
                                                        return number_format($total, 2) . ' ' . strtoupper($currency);
                                                    }),
                                                Placeholder::make('live_balance_due')
                                                    ->label('Balance Due')
                                                    ->color('danger')
                                                    ->content(function (Get $get, $record) {
                                                        $status = $get('status');
                                                        $currency = $get('currency') ?? $record?->currency ?? 'USD';

                                                        // If status is Paid, balance is 0
                                                        if ($status === 'Paid') {
                                                            return new HtmlString('<div class="text-xl font-black text-emerald-600">0.00 ' . strtoupper($currency) . '</div>');
                                                        }

                                                        $items = $get('items') ?? [];
                                                        $total = collect($items)->sum(fn($item) => (float) ($item['amount'] ?? 0));

                                                        return new HtmlString('
                                                            <div class="text-xl font-black text-red-600 underline underline-offset-4 decoration-2">
                                                                ' . number_format($total, 2) . ' ' . strtoupper($currency) . '
                                                            </div>
                                                        ');
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Invoice Items')
                                    ->icon('heroicon-o-list-bullet')
                                    ->compact()
                                    ->schema([
                                        Repeater::make('items')
                                            ->relationship('items')
                                            ->live()        // Ensure changes update the form state immediately
                                            ->schema([
                                                TextInput::make('description')
                                                    ->placeholder('Enter item description...')
                                                    ->required()
                                                    ->columnSpan(9),
                                                TextInput::make('amount')
                                                    ->numeric()
                                                    ->required()
                                                    ->live() // Update totals on keyup/change
                                                    ->formatStateUsing(fn($state) => $state / 100)
                                                    ->dehydrateStateUsing(fn($state) => $state * 100)
                                                    ->columnSpan(3),
                                            ])
                                            ->columns(12)
                                            ->addable(true)
                                            ->deletable(true)
                                            ->addActionLabel('Add Item'),
                                    ]),
                            ]),
                        Tab::make('Add Payment')
                            ->icon('heroicon-m-credit-card')
                            ->schema([
                                Section::make('Process Payment')
                                    ->description('Capture or record a manual payment for this invoice.')
                                    ->schema([
                                        Placeholder::make('live_balance_due')
                                            ->label('Balance Due')
                                            ->color('danger')
                                            ->content(function (Get $get, $record) {
                                                $status = $get('status');
                                                $currency = $get('currency') ?? $record?->currency ?? 'USD';

                                                // If status is Paid, balance is 0
                                                if ($status === 'Paid') {
                                                    return new HtmlString('<div class="text-xl font-black text-emerald-600">0.00 ' . strtoupper($currency) . '</div>');
                                                }

                                                $items = $get('items') ?? [];
                                                $total = collect($items)->sum(fn($item) => (float) ($item['amount'] ?? 0));

                                                return new HtmlString('
                                                            <div class="text-xl font-black text-red-600 underline underline-offset-4 decoration-2">
                                                                ' . number_format($total, 2) . ' ' . strtoupper($currency) . '
                                                            </div>
                                                        ');
                                            }),
                                        TextInput::make('payment_amount')->numeric()->prefix('$'),
                                        Select::make('payment_gateway')->options(['Stripe' => 'Stripe', 'PayPal' => 'PayPal', 'Manual' => 'Manual']),
                                    ]),
                            ]),
                        Tab::make('Options')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Section::make('Invoice Configuration')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('invoice_number')
                                                    ->label('Invoice #')
                                                    ->disabled()
                                                    ->required()
                                                    ->unique(ignoreRecord: true),
                                                Select::make('status')
                                                    ->options([
                                                        'Unpaid' => 'Unpaid',
                                                        'Partially Paid' => 'Partially Paid',
                                                        'Paid' => 'Paid',
                                                        'Cancelled' => 'Cancelled',
                                                        'Refunded' => 'Refunded',
                                                        'Collections' => 'Collections',
                                                    ])
                                                    ->live()
                                                    ->required(),
                                                Select::make('currency')
                                                    ->options([
                                                        'USD' => 'USD',
                                                        'EUR' => 'EUR',
                                                        'GBP' => 'GBP',
                                                        'BDT' => 'BDT',
                                                    ])
                                                    ->default('USD')
                                                    ->live()
                                                    ->required(),
                                                DatePicker::make('paid_at')
                                                    ->label('Paid Date'),
                                                TextInput::make('tax')
                                                    ->label('Tax Amount')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->default(0)
                                                    ->formatStateUsing(fn($state) => $state / 100)
                                                    ->dehydrateStateUsing(fn($state) => $state * 100)
                                                    ->helperText('Additional tax to add to subtotal'),
                                                TextInput::make('discount')
                                                    ->label('Discount Amount')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->default(0)
                                                    ->formatStateUsing(fn($state) => $state / 100)
                                                    ->dehydrateStateUsing(fn($state) => $state * 100)
                                                    ->helperText('Discount to subtract from subtotal'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Credit')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Section::make('Credit Control')
                                    ->description('Apply existing customer credits to this specific invoice.')
                                    ->schema([
                                        TextInput::make('credit_to_apply')
                                            ->label('Amount to Apply')
                                            ->numeric()
                                            ->prefix('$'),
                                    ]),
                            ]),
                        // Tab::make('Refund')
                        //     ->icon('heroicon-m-arrow-path-rounded-square')
                        //     ->schema([
                        //         Section::make('Refund Management')
                        //             ->description('Process a full or partial refund back to the original funding source.')
                        //             ->schema([
                        //                 TextInput::make('refund_amount')->numeric()->prefix('$'),
                        //             ]),
                        //     ]),
                        Tab::make('Notes')
                            ->icon('heroicon-m-chat-bubble-bottom-center-text')
                            ->schema([
                                Section::make('Administrative Commentary')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Internal Notes')
                                            ->placeholder('Log internal adjustments, exceptions, or audit notes here...')
                                            ->rows(10),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
