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
                                            ->icon('heroicon-o-information-circle')
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('overview_details')
                                                    ->hiddenLabel()
                                                    ->content(fn($record) => $record ? new HtmlString('
                                                        <div class="flex flex-col space-y-3 py-1 min-h-[148px]">
                                                            <div class="flex justify-between border-b border-gray-50 pb-2">
                                                                <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest"><b>Customer :</b></span>
                                                                <span class="text-xs font-bold text-gray-900">' . ($record->user?->name ?? 'Guest') . '</span>
                                                            </div>
                                                            <div class="flex justify-between border-b border-gray-50 pb-2">
                                                                <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest"><b>Status :</b></span>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest" style="background-color: ' . match ($record->status) {
                                                        'Paid' => '#dcfce7; color: #166534;',
                                                        'Unpaid' => '#fee2e2; color: #991b1b;',
                                                        'Cancelled' => '#f1f5f9; color: #475569;',
                                                        'Refunded' => '#dbeafe; color: #1e40af;',
                                                        default => '#f1f5f9; color: #475569;',
                                                    } . '">
                                                                    ' . $record->status . '
                                                                </span>
                                                            </div>
                                                            <div class="flex justify-between border-b border-gray-50 pb-2">
                                                                <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest"><b>Created Date :</b></span>
                                                                <span class="text-xs font-medium text-gray-600">' . ($record->date?->format('M d, Y') ?? '-') . '</span>
                                                            </div>
                                                            <div class="flex justify-between">
                                                                <span class="text-[11px] font-black text-gray-400 uppercase tracking-widest"><b>Due Date :</b></span>
                                                                <span class="text-xs font-medium text-gray-600">' . ($record->due_date?->format('M d, Y') ?? '-') . '</span>
                                                            </div>
                                                        </div>
                                                    ') : null),
                                            ]),

                                        Section::make('Transaction History')
                                            ->icon('heroicon-o-credit-card')
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('transaction_details')
                                                    ->hiddenLabel()
                                                    ->content(function ($record) {
                                                        if (!$record || $record->transactions->isEmpty()) {
                                                            return new HtmlString('<div class="flex flex-col items-center justify-center py-8 opacity-30 min-h-[148px] text-center"><svg style="width:24px;height:24px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span class="text-[10px] mt-1 font-black tracking-widest uppercase text-center leading-tight">No Transactions<br>Recorded</span></div>');
                                                        }

                                                        $rows = $record->transactions->sortByDesc('date')->take(4)->map(function ($transaction) use ($record) {
                                                            $amount = number_format($transaction->amount / 100, 2);
                                                            $date = $transaction->date ? $transaction->date->format('M d, H:i') : '-';
                                                            $method = $transaction->payment_method ?? '-';

                                                            return "
                                                                <tr class='border-b border-gray-50 hover:bg-gray-50/50 transition-colors'>
                                                                    <td class='py-2 px-1 text-[10px] text-gray-400 font-medium'>{$date}</td>
                                                                    <td class='py-2 px-2 text-[10px] font-bold text-gray-600 truncate max-w-[80px]'>{$method}</td>
                                                                    <td class='py-2 px-1 text-[11px] text-right font-black text-gray-900'>{$amount}</td>
                                                                </tr>
                                                            ";
                                                        })->implode('');

                                                        return new HtmlString("
                                                            <div class='-mx-2 mt-[-4px] min-h-[148px]'>
                                                                <table class='w-full text-left'>
                                                                    <thead class='bg-gray-50/30'>
                                                                        <tr>
                                                                            <th class='pb-1 text-[9px] font-black tracking-widest text-gray-400 uppercase px-1'><b>Date & Time</b></th>
                                                                            <th class='pb-1 px-2 text-[9px] font-black tracking-widest text-gray-400 uppercase'><b>Mode</b></th>
                                                                            <th class='pb-1 text-[9px] font-black tracking-widest text-gray-400 uppercase text-right px-1'><b>Sum</b></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {$rows}
                                                                    </tbody>
                                                                </table>
                                                                " . ($record->transactions->count() > 4 ? "<div class='text-[8px] text-center pt-2 text-blue-500 font-bold uppercase tracking-tighter'>See all below →</div>" : "") . "
                                                            </div>
                                                        ");
                                                    }),
                                            ]),

                                        Section::make('Financials')
                                            ->icon('heroicon-o-banknotes')
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('financial_summary')
                                                    ->hiddenLabel()
                                                    ->content(function (Get $get, $record) {
                                                        $status = $get('status');
                                                        $currency = $get('currency') ?? $record?->currency ?? 'USD';
                                                        $items = $get('items') ?? [];
                                                        $total = collect($items)->sum(fn($item) => (float) ($item['amount'] ?? 0));

                                                        $paidInCents = $record ? $record->transactions()->sum('amount') : 0;
                                                        $paidInDollars = $paidInCents / 100;
                                                        $balanceDue = ($status === 'Paid') ? 0 : max(0, $total - $paidInDollars);

                                                        $accentColor = $balanceDue > 0 ? '#ef4444' : '#10b981';

                                                        return new HtmlString('
                                                            <div class="flex flex-col min-h-[152px] relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm group">
                                                                <!-- Top Accent Bar -->
                                                                <div class="h-1.5 w-full" style="background: linear-gradient(90deg, ' . $accentColor . ' 0%, ' . $accentColor . 'dd 100%);"></div>
                                                                
                                                                <div class="p-4 flex flex-col flex-grow">
                                                                    <div class="flex justify-between items-start mb-2">
                                                                        <div class="flex flex-col">
                                                                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total Amount</span>
                                                                            <div class="flex items-baseline space-x-1">
                                                                                <span class="text-xl font-black text-gray-700 tracking-tight">' . number_format($total, 2) . '</span>
                                                                                <span class="text-[9px] font-bold text-gray-400 uppercase">' . $currency . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="p-2 rounded-xl bg-gray-50 border border-gray-100 group-hover:scale-110 transition-transform duration-500">
                                                                            <svg style="width:16px;height:16px" class="text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="mt-auto flex flex-col items-end">
                                                                        <div class="flex items-center space-x-1.5 mb-1">
                                                                            <div class="flex h-1.5 w-1.5 relative">
                                                                                ' . ($balanceDue > 0 ? '<span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background-color: ' . $accentColor . '"></span>' : '') . '
                                                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background-color: ' . $accentColor . '"></span>
                                                                            </div>
                                                                            <span class="text-[11px] font-black uppercase tracking-[0.1em]" style="color: ' . $accentColor . '">Balance Due</span>
                                                                        </div>
                                                                        
                                                                        <div class="flex items-baseline space-x-1.5">
                                                                            <span class="text-5xl font-black tracking-tighter tabular-nums" style="color: ' . $accentColor . '; text-shadow: 0 2px 4px rgba(0,0,0,0.05);">' . number_format($balanceDue, 2) . '</span>
                                                                            <span class="text-xs font-black uppercase opacity-60" style="color: ' . $accentColor . '">' . $currency . '</span>
                                                                        </div>
                                                                        
                                                                        ' . ($paidInDollars > 0 ? '
                                                                        <div class="mt-2 px-2 py-0.5 rounded-full border border-emerald-100 bg-emerald-50/50">
                                                                            <span class="text-[9px] font-black text-emerald-600 uppercase tracking-tight">Received: ' . number_format($paidInDollars, 2) . ' ' . $currency . '</span>
                                                                        </div>' : '') . '
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Subtle Corner Decoration -->
                                                                <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full opacity-[0.03] group-hover:opacity-[0.06] transition-opacity duration-700" style="background-color: ' . $accentColor . '"></div>
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
                                    ->description('Capture or record a manual payment for this invoice. Use the "Add Payment" button in the header for immediate processing.')
                                    ->schema([
                                        Placeholder::make('live_balance_due_payment')
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
                                        TextInput::make('payment_amount')
                                            ->label('Payment amount')
                                            ->numeric()
                                            ->prefix('$')
                                            ->placeholder('0.00')
                                            ->helperText('Enter the amount to process for this invoice'),
                                        Select::make('payment_gateway')
                                            ->label('Payment gateway')
                                            ->placeholder('Select an option')
                                            ->options([
                                                'Stripe' => 'Stripe',
                                                'PayPal' => 'PayPal',
                                                'Bank Transfer' => 'Bank Transfer',
                                                'Cash' => 'Cash',
                                                'Check' => 'Check',
                                                'Manual' => 'Manual',
                                            ])
                                            ->helperText('Select the payment method used for this transaction'),
                                        TextInput::make('transaction_id_payment')
                                            ->label('Transaction ID')
                                            ->placeholder('e.g., ch_3ABC123xyz...')
                                            ->helperText('Optional: Gateway transaction reference number or check number'),
                                        Textarea::make('payment_notes')
                                            ->label('Payment Notes')
                                            ->placeholder('Add any additional notes about this payment...')
                                            ->rows(3)
                                            ->helperText('Internal notes about this payment'),
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
