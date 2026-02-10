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
            ->columns(1)
            ->components([
                // Large Invoice Number Header
                Placeholder::make('page_header_invoice')
                    ->hiddenLabel()
                    ->content(fn($record) => $record ? new HtmlString('
                            <p style="font-size: 20px; font-weight: bold;">
                                Invoice Number # ' . $record->invoice_number . '
                            </p>
                    ') : null),

                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Section::make('Invoice Details')
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('user.name')
                                            ->label('Customer')
                                            ->size('lg')
                                            ->weight('bold'),
                                        TextEntry::make('user.email')
                                            ->label('Email')
                                            ->copyable()
                                            ->icon('heroicon-m-envelope'),
                                        TextEntry::make('date')
                                            ->date('F j, Y')
                                            ->label('Invoice Date')
                                            ->icon('heroicon-m-calendar'),
                                        TextEntry::make('due_date')
                                            ->date('F j, Y')
                                            ->label('Due Date')
                                            ->icon('heroicon-m-calendar-days')
                                            ->color(fn($record) => $record->due_date < now() && $record->status !== 'Paid' ? 'danger' : 'gray'),
                                        TextEntry::make('paid_at')
                                            ->date('F j, Y - g:i A')
                                            ->label('Paid Date')
                                            ->placeholder('Not paid yet')
                                            ->icon('heroicon-m-check-circle')
                                            ->color('success'),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn($record) => $record->status === 'Paid' ? 'success' : 'danger')
                                            ->columnSpan(1),
                                    ]),
                            ]),

                        Section::make('Financial Summary')
                            ->columnSpan(1)
                            ->schema([
                                Placeholder::make('financial_breakdown')
                                    ->hiddenLabel()
                                    ->content(function ($record) {
                                        $currency = strtoupper($record->currency);
                                        $tax = number_format(($record->tax ?? 0) / 100, 2);
                                        $discount = number_format(($record->discount ?? 0) / 100, 2);
                                        $total = number_format(($record->total ?? 0) / 100, 2);
                                        $paid = number_format($record->total_paid / 100, 2);
                                        $creditApplied = number_format(($record->credit_applied ?? 0) / 100, 2);
                                        $balance = number_format(($record->balance_due ?? $record->calculateBalanceDue()) / 100, 2);

                                        return new HtmlString('
                                            <div class="space-y-3 text-sm">
                                                ' . ($record->tax > 0 ? '
                                                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                                                    <span class="text-gray-600 dark:text-gray-400">Tax:</span>
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100">' . $tax . ' ' . $currency . '</span>
                                                </div>' : '') . '
                                                ' . ($record->discount > 0 ? '
                                                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                                                    <span class="text-gray-600 dark:text-gray-400">Discount:</span>
                                                    <span class="font-semibold text-green-600 dark:text-green-400">-' . $discount . ' ' . $currency . '</span>
                                                </div>' : '') . '
                                                <div class="flex justify-between py-2 border-b-2 border-gray-300 dark:border-gray-600">
                                                    <span class="font-bold text-gray-700 dark:text-gray-300">Total:</span>
                                                    <span class="font-black text-lg text-gray-900 dark:text-gray-100">' . $total . ' ' . $currency . '</span>
                                                </div>
                                                ' . ($paid > 0 ? '
                                                <div class="flex justify-between py-2">
                                                    <span class="text-gray-600 dark:text-gray-400">Paid:</span>
                                                    <span class="font-semibold text-green-600 dark:text-green-400">-' . $paid . ' ' . $currency . '</span>
                                                </div>' : '') . '
                                                ' . ($record->credit_applied > 0 ? '
                                                <div class="flex justify-between py-2">
                                                    <span class="text-gray-600 dark:text-gray-400">Credit Applied:</span>
                                                    <span class="font-semibold text-blue-600 dark:text-blue-400">-' . $creditApplied . ' ' . $currency . '</span>
                                                </div>' : '') . '
                                                <div class="flex justify-between py-3 px-4 bg-' . ($balance > 0 ? 'red' : 'green') . '-50 dark:bg-' . ($balance > 0 ? 'red' : 'green') . '-900/20 rounded-lg mt-2">
                                                    <span class="font-bold text-' . ($balance > 0 ? 'red' : 'green') . '-700 dark:text-' . ($balance > 0 ? 'red' : 'green') . '-300">Balance Due:</span>
                                                    <span class="font-black text-xl text-' . ($balance > 0 ? 'red' : 'green') . '-600 dark:text-' . ($balance > 0 ? 'red' : 'green') . '-400">' . $balance . ' ' . $currency . '</span>
                                                </div>
                                            </div>
                                        ');
                                    }),
                            ]),
                    ]),

                Section::make('Invoice Items')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Products and services included in this invoice')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('description')
                                    ->columnSpan(3)
                                    ->weight('medium'),
                                TextEntry::make('amount')
                                    ->formatStateUsing(fn($state, $record) => number_format($state / 100, 2) . ' ' . strtoupper($record->invoice->currency))
                                    ->weight('bold')
                                    ->columnSpan(1),
                            ])
                            ->columns(5)
                    ]),

                Section::make('Payment Transactions')
                    ->icon('heroicon-o-banknotes')
                    ->description('Payment history for this invoice')
                    ->collapsible()
                    ->collapsed(fn($record) => $record->transactions->count() === 0)
                    ->schema([
                        RepeatableEntry::make('transactions')
                            ->schema([
                                TextEntry::make('date')
                                    ->date('M d, Y - g:i A')
                                    ->icon('heroicon-m-calendar')
                                    ->columnSpan(2),
                                TextEntry::make('payment_method')
                                    ->icon('heroicon-m-credit-card')
                                    ->badge()
                                    ->columnSpan(1),
                                TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->placeholder('N/A')
                                    ->copyable()
                                    ->columnSpan(2),
                                TextEntry::make('amount')
                                    ->formatStateUsing(
                                        fn($state, $record) =>
                                        ($state < 0 ? '-' : '+') . number_format(abs($state) / 100, 2) . ' ' . strtoupper($record->invoice->currency)
                                    )
                                    ->weight('bold')
                                    ->color(fn($state) => $state < 0 ? 'danger' : 'success')
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->placeholder(new HtmlString('<div class="text-center py-4 text-gray-500">No transactions recorded yet.</div>'))
                    ]),

                Section::make('Credits Applied')
                    ->icon('heroicon-o-gift')
                    ->description('Customer credits used for this invoice')
                    ->collapsible()
                    ->collapsed(fn($record) => $record->credits->count() === 0)
                    ->schema([
                        RepeatableEntry::make('credits')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Date')
                                    ->date('M d, Y - g:i A')
                                    ->icon('heroicon-m-calendar')
                                    ->columnSpan(2),
                                TextEntry::make('description')
                                    ->columnSpan(3),
                                TextEntry::make('type')
                                    ->badge()
                                    ->columnSpan(1),
                                TextEntry::make('amount')
                                    ->formatStateUsing(
                                        fn($state, $record) =>
                                        number_format(abs($state) / 100, 2) . ' ' . strtoupper($record->user->balance ? 'USD' : 'USD')
                                    )
                                    ->weight('bold')
                                    ->color('info')
                                    ->columnSpan(1),
                            ])
                            ->columns(7)
                            ->placeholder(new HtmlString('<div class="text-center py-4 text-gray-500">No credits applied to this invoice.</div>'))
                    ]),

                Section::make('Notes')
                    ->icon('heroicon-o-document-text')
                    ->description('Internal administrative notes')
                    ->collapsible()
                    ->collapsed(fn($record) => empty($record->notes))
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('No notes')
                            ->prose()
                            ->markdown(),
                    ]),
            ]);
    }
}
