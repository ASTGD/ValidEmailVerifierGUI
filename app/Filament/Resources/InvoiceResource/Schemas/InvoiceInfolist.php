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

                Grid::make(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 3])
                    ->schema([
                        // Card 1: Invoice Overview
                        Section::make('Invoice Overview')
                            ->icon('heroicon-o-information-circle')
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
                                        'Partially Paid' => 'background: #ffedd5; color: #9a3412;',
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
                                                <span style="font-size: 12px; color: #334155; font-weight: 500;">' . ($record->due_date?->format('F j, Y') ?? '-') . '</span>
                                            </div>
                                        </div>
                                    ') : null),
                            ]),

                        // Card 2: Transaction History
                        Section::make('Transaction History')
                            ->icon('heroicon-o-credit-card')
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
                                                " . ($record->transactions->count() > 1 ? "<div style='text-align: center; border-top: 1px dashed #e2e8f0; margin-top: auto; padding-top: 8px;'><a href='#detailed-transactions-view' style='font-size: 10px; color: #3b82f6; font-weight: 700; text-transform: uppercase; text-decoration: none; letter-spacing: 0.05em;'>See all " . ($record->transactions->count()) . " records below ↓</a></div>" : "") . "
                                            </div>
                                        ");
                                    }),
                            ]),

                        // Card 3: Financials
                        Section::make('Financials')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Placeholder::make('financial_breakdown')
                                    ->hiddenLabel()
                                    ->content(function ($record) {
                                        $currency = strtoupper($record->currency ?? 'USD');
                                        // Calculate dynamically from items to ensure accuracy in View mode
                                        $totalInCents = $record->calculateTotal();
                                        $totalInDollars = $totalInCents / 100;

                                        $paidInCents = $record->transactions()->sum('amount');
                                        $creditAppliedInCents = $record->credit_applied ?? 0;

                                        $receivedInDollars = ($paidInCents + $creditAppliedInCents) / 100;
                                        $balanceDue = ($record->status === 'Paid') ? 0 : max(0, $totalInDollars - $receivedInDollars);

                                        $accentColor = $balanceDue > 0 ? '#dc2626' : '#16a34a';

                                        return new HtmlString('
                                            <div style="display: flex; flex-direction: column; min-height: 180px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; position: relative; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
                                                <div style="height: 5px; width: 100%; background: ' . $accentColor . ';"></div>
                                                <div style="padding: 20px; display: flex; flex-direction: column; height: 100%;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 10px;">
                                                        <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total Amount</span>
                                                        <span style="font-size: 13px; color: #1e293b; font-weight: 500;">' . number_format($totalInDollars, 2) . ' ' . $currency . '</span>
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

                Section::make('Detailed Transaction Log')
                    ->icon('heroicon-o-clock')
                    ->id('detailed-transactions-view')
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
                                    $currency = strtoupper($record->currency ?? 'USD');
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
