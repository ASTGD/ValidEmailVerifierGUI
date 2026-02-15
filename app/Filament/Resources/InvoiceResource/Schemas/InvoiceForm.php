<?php

namespace App\Filament\Resources\InvoiceResource\Schemas;

use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
                                Placeholder::make('publication_banner')
                                    ->hiddenLabel()
                                    ->visible(fn($record) => $record && !$record->is_published)
                                    ->content(fn($record) => new HtmlString('
                                        <div class="flex items-center justify-between p-4 mb-4 border border-amber-200 rounded-xl bg-amber-50 dark:bg-amber-900/10 dark:border-amber-500/20">
                                            <div class="flex items-center space-x-3">
                                                <div class="p-2 bg-amber-100 rounded-lg dark:bg-amber-500/20">
                                                </div>
                                                <div>
                                                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-400">Invoice is in Draft Mode</h3>
                                                    <p class="text-[11px] text-amber-700 dark:text-amber-500/80 uppercase font-black tracking-widest">Only visible to administrators</p>
                                                </div>
                                            </div>
                                        </div>
                                    '))
                                    ->hintAction(
                                        Action::make('publish')
                                            ->label('Publish Now')
                                            ->icon('heroicon-m-paper-airplane')
                                            ->color('success')
                                            ->requiresConfirmation()
                                            ->action(function ($record) {
                                                $record->update(['is_published' => true]);
                                                Notification::make()
                                                    ->title('Invoice Published')
                                                    ->success()
                                                    ->send();
                                            })
                                    ),

                                Grid::make(['default' => 1, 'lg' => 3])
                                    ->schema([
                                        Section::make('Invoice Overview')
                                            ->icon('heroicon-o-information-circle')
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('overview_details')
                                                    ->hiddenLabel()
                                                    ->content(fn($record) => $record ? new HtmlString('
                                                        <div style="display: flex; flex-direction: column; min-height: 180px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Customer</span>
                                                                <span style="font-size: 13px; color: #1e293b; font-weight: 500;">' . ($record->user?->name ?? 'Guest') . '</span>
                                                            </div>
                                                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Publication</span>
                                                                <span style="padding: 3px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; ' . ($record->is_published ? 'background: #dcfce7; color: #166534;' : 'background: #fef3c7; color: #92400e;') . '">
                                                                    ' . ($record->is_published ? 'PUBLISHED' : 'DRAFT') . '
                                                                </span>
                                                            </div>
                                                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Status</span>
                                                                <span style="padding: 3px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; ' . match ($record->status) {
                                                        'Paid' => 'background: #dcfce7; color: #166534;',
                                                        'Unpaid' => 'background: #fee2e2; color: #991b1b;',
                                                        'Cancelled' => 'background: #f1f5f9; color: #475569;',
                                                        'Refunded' => 'background: #dbeafe; color: #1e40af;',
                                                        default => 'background: #f1f5f9; color: #475569;',
                                                    } . '">
                                                                    ' . $record->status . '
                                                                </span>
                                                            </div>
                                                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Created Date</span>
                                                                <span style="font-size: 12px; color: #334155; font-weight: 500;">' . ($record->date?->format('M d, Y') ?? '-') . '</span>
                                                            </div>
                                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Due Date</span>
                                                                <span style="font-size: 12px; color: #334155; font-weight: 500;">' . ($record->due_date?->format('M d, Y') ?? '-') . '</span>
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
                                                            return new HtmlString('<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px; background: #ffffff; border: 2px dashed #e2e8f0; border-radius: 12px;"><svg style="width:32px;height:32px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><span style="font-size: 11px; margin-top: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; text-align: center;">No Payments Recorded</span></div>');
                                                        }

                                                        $latest = $record->transactions->sortByDesc('date')->first();
                                                        $amount = number_format($latest->amount / 100, 2);
                                                        $date = $latest->date ? $latest->date->format('M d, Y') : '-';
                                                        $method = $latest->payment_method ?? '-';
                                                        $txId = $latest->transaction_id ?? '-';
                                                        $currency = $record->currency ?? 'USD';

                                                        return new HtmlString("
                                                            <div style='display: flex; flex-direction: column; min-height: 180px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);'>
                                                                <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;'>
                                                                    <span style='font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;'>Last Date</span>
                                                                    <span style='font-size: 13px; color: #1e293b; font-weight: 500;'>{$date}</span>
                                                                </div>
                                                                <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;'>
                                                                    <span style='font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;'>Method</span>
                                                                    <span style='background: #f8fafc; color: #475569; border: 1px solid #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase;'>{$method}</span>
                                                                </div>
                                                                <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;'>
                                                                    <span style='font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;'>Trans ID</span>
                                                                    <span style='font-size: 12px; font-family: monospace; color: #475569; font-weight: 400;'>{$txId}</span>
                                                                </div>
                                                                <div style='display: flex; justify-content: space-between; align-items: center;'>
                                                                    <span style='font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;'>Amount</span>
                                                                    <span style='font-size: 13px; color: #059669; font-weight: 700;'>{$amount} {$currency}</span>
                                                                </div>
                                                                " . ($record->transactions->count() > 1 ? "<div style='text-align: center; border-top: 1px dashed #e2e8f0; margin-top: auto; padding-top: 8px;'><a href='#detailed-transactions' style='font-size: 10px; color: #3b82f6; font-weight: 700; text-transform: uppercase; text-decoration: none; letter-spacing: 0.05em;'>See all " . ($record->transactions->count()) . " records below ↓</a></div>" : "") . "
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

                                                        // Use (float) to handle decimal values from the form state (dollars)
                                                        $subtotal = collect($items)->sum(fn($item) => (float) ($item['amount'] ?? 0));
                                                        $tax = (float) ($get('tax') ?? 0);
                                                        $discount = (float) ($get('discount') ?? 0);
                                                        $total = max(0, $subtotal + $tax - $discount);

                                                        $paidInCents = $record ? $record->transactions()->sum('amount') : 0;
                                                        $creditAppliedInCents = $record ? ($record->credit_applied ?? 0) : 0;

                                                        $receivedInDollars = ($paidInCents + $creditAppliedInCents) / 100;
                                                        $balanceDue = ($status === 'Paid') ? 0 : max(0, $total - $receivedInDollars);

                                                        $accentColor = $balanceDue > 0 ? '#dc2626' : '#16a34a';

                                                        return new HtmlString('
                                                            <div style="display: flex; flex-direction: column; min-height: 180px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; position: relative; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
                                                                <div style="height: 5px; width: 100%; background: ' . $accentColor . ';"></div>
                                                                <div style="padding: 20px; display: flex; flex-direction: column; height: 100%;">
                                                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                        <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total Amount</span>
                                                                        <span style="font-size: 13px; color: #1e293b; font-weight: 500;">' . number_format($total, 2) . ' ' . $currency . '</span>
                                                                    </div>
                                                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                                        <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Received</span>
                                                                        <span style="font-size: 13px; color: #059669; font-weight: 600;">' . number_format($receivedInDollars, 2) . ' ' . $currency . '</span>
                                                                    </div>
                                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                                                                        <span style="font-size: 11px; color: ' . $accentColor . '; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Balance Due</span>
                                                                        <span style="font-size: 16px; color: ' . $accentColor . '; font-weight: 800;">' . number_format($balanceDue, 2) . ' ' . $currency . '</span>
                                                                    </div>
                                                                </div>
                                                                <div style="position: absolute; right: -15px; bottom: -15px; width: 70px; height: 70px; border-radius: 50%; background: ' . $accentColor . '; opacity: 0.05;"></div>
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

                                Section::make('Detailed Transaction Log')
                                    ->icon('heroicon-o-clock')
                                    ->id('detailed-transactions')
                                    ->collapsible()
                                    ->visible(fn($record) => $record && $record->transactions->count() > 0)
                                    ->schema([
                                        Placeholder::make('all_transactions_table')
                                            ->hiddenLabel()
                                            ->content(function ($record) {
                                                if (!$record)
                                                    return null;

                                                $rows = $record->transactions->sortByDesc('date')->map(function ($tx) use ($record) {
                                                    $date = $tx->date ? $tx->date->format('M d, Y - H:i') : '-';
                                                    $amount = number_format($tx->amount / 100, 2);
                                                    $currency = $record->currency ?? 'USD';
                                                    $color = $tx->amount > 0 ? '#059669' : '#dc2626';
                                                    return "
                                                        <tr style='border-bottom: 1px solid #f8fafc;'>
                                                            <td style='padding: 14px 12px; font-size: 12px; font-weight: 500; color: #334155;'>{$date}</td>
                                                            <td style='padding: 14px 12px;'>
                                                                <span style='background: #f8fafc; color: #64748b; border: 1px solid #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase;'>{$tx->payment_method}</span>
                                                            </td>
                                                            <td style='padding: 14px 12px; font-size: 12px; font-family: monospace; color: #94a3b8; font-weight: 400;'>{$tx->transaction_id}</td>
                                                            <td style='padding: 14px 12px; text-align: right; font-size: 13px; font-weight: 700; color: {$color};'>
                                                                " . ($tx->amount > 0 ? '+ ' : '') . "{$amount} {$currency}
                                                            </td>
                                                        </tr>
                                                    ";
                                                })->implode('');

                                                return new HtmlString("
                                                    <div style='width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); margin-top: 10px;'>
                                                        <table style='width: 100%; border-collapse: collapse; table-layout: fixed;'>
                                                            <thead>
                                                                <tr style='background: #f8fafc; border-bottom: 2px solid #f1f5f9;'>
                                                                    <th style='padding: 12px; text-align: left; width: 25%; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;'>Date / Time</th>
                                                                    <th style='padding: 12px; text-align: left; width: 20%; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;'>Method</th>
                                                                    <th style='padding: 12px; text-align: left; width: 35%; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;'>Transaction ID</th>
                                                                    <th style='padding: 12px; text-align: right; width: 20%; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;'>Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {$rows}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                ");
                                            }),
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

                                                $paidInCents = $record ? $record->transactions()->sum('amount') : 0;
                                                $paidInDollars = $paidInCents / 100;
                                                $balanceDue = ($status === 'Paid') ? 0 : max(0, $total - $paidInDollars);

                                                return new HtmlString('
                                                            <div class="text-xl font-black text-red-600 underline underline-offset-4 decoration-2">
                                                                ' . number_format($balanceDue, 2) . ' ' . strtoupper($currency) . '
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
                                                Toggle::make('is_published')
                                                    ->label('Published')
                                                    ->helperText('Enable to make this invoice visible to the customer')
                                                    ->default(false),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Credit')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Section::make('Credit Control')
                                    ->description('Apply existing customer credits to this specific invoice.')
                                    ->schema([
                                        Placeholder::make('available_credit_display')
                                            ->label('Available Customer Credit')
                                            ->content(function ($record) {
                                                if (!$record)
                                                    return '0.00 USD';
                                                $availableCredit = \App\Models\Credit::where('user_id', $record->user_id)
                                                    ->whereNull('invoice_id')
                                                    ->where('amount', '>', 0)
                                                    ->sum('amount');
                                                return '$' . number_format($availableCredit / 100, 2);
                                            }),
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
