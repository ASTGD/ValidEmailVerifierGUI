<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationOrderStatus;
use App\Models\User;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;


class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Left Column
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Client Information')
                                    ->schema([
                                        TextEntry::make('first_name')->label('First Name')->placeholder('-'),
                                        TextEntry::make('last_name')->label('Last Name')->placeholder('-'),
                                        TextEntry::make('company_name')->label('Company Name')->placeholder('No company set'),
                                        TextEntry::make('email')->label('Email Address')->copyable(),
                                        TextEntry::make('address_1')->label('Address 1')->placeholder('-'),
                                        TextEntry::make('address_2')->label('Address 2')->placeholder('-'),
                                        TextEntry::make('city')->label('City')->placeholder('-'),
                                        TextEntry::make('state')->label('State/Region')->placeholder('-'),
                                        TextEntry::make('postcode')->label('Postcode')->placeholder('-'),
                                        TextEntry::make('country')->label('Country')->placeholder('-'),
                                        TextEntry::make('phone')->label('Phone Number')->placeholder('-'),
                                    ])->columns(2),

                                Section::make('Contacts')
                                    ->schema([
                                        TextEntry::make('contacts_status')
                                            ->label('Contacts Status')
                                            ->default('No additional contacts setup')
                                            ->hiddenLabel(),
                                    ]),
                            ]),

                        // Middle Column
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Invoices/Billing')
                                    ->schema([
                                        TextEntry::make('paid_invoices')
                                            ->label('Paid')
                                            ->state(function (User $record) {
                                                $orders = $record->verificationOrders->filter(fn($o) => $o->checkoutIntent?->status === CheckoutIntentStatus::Completed && !$o->refunded_at);
                                                return $orders->count() . ' (' . number_format($orders->sum('amount_cents') / 100, 2) . ' ' . strtoupper($record->currency ?: 'USD') . ')';
                                            }),
                                        TextEntry::make('unpaid_invoices')
                                            ->label('Unpaid')
                                            ->state(function (User $record) {
                                                $orders = $record->verificationOrders->filter(fn($o) => $o->checkoutIntent?->status === CheckoutIntentStatus::Pending);
                                                return $orders->count() . ' (' . number_format($orders->sum('amount_cents') / 100, 2) . ' ' . strtoupper($record->currency ?: 'USD') . ')';
                                            }),
                                        TextEntry::make('cancelled_invoices')
                                            ->label('Cancelled')
                                            ->state(function (User $record) {
                                                $orders = $record->verificationOrders->where('status', VerificationOrderStatus::Cancelled);
                                                return $orders->count() . ' (' . number_format($orders->sum('amount_cents') / 100, 2) . ' ' . strtoupper($record->currency ?: 'USD') . ')';
                                            }),
                                        TextEntry::make('refunded_invoices')
                                            ->label('Refunded')
                                            ->state(function (User $record) {
                                                $orders = $record->verificationOrders->whereNotNull('refunded_at');
                                                return $orders->count() . ' (' . number_format($orders->sum('amount_cents') / 100, 2) . ' ' . strtoupper($record->currency ?: 'USD') . ')';
                                            }),
                                        TextEntry::make('collections_invoices')
                                            ->label('Collections')
                                            ->state(fn(User $record) => '0 (0.00 ' . strtoupper($record->currency ?: 'USD') . ')'),
                                        TextEntry::make('income')
                                            ->label('Income')
                                            ->state(function (User $record) {
                                                $sum = $record->verificationOrders->filter(fn($o) => $o->checkoutIntent?->status === CheckoutIntentStatus::Completed && !$o->refunded_at)->sum('amount_cents');
                                                return number_format($sum / 100, 2) . ' ' . strtoupper($record->currency ?: 'USD');
                                            }),
                                        TextEntry::make('credit_balance')
                                            ->label('Credit Balance')
                                            ->state(fn(User $record) => '0.00 ' . strtoupper($record->currency ?: 'USD')),
                                    ])->columns(2),

                                Section::make('Other Information')
                                    ->schema([
                                        TextEntry::make('status')->badge()->color(fn(string $state): string => match ($state) {
                                            'active' => 'success',
                                            'inactive' => 'gray',
                                            'closed' => 'danger',
                                            default => 'warning',
                                        }),
                                        TextEntry::make('client_group')->label('Client Group')->default('None'),
                                        TextEntry::make('created_at')->label('Signup Date')->date(),
                                        TextEntry::make('last_login')
                                            ->label('Last Login')
                                            ->state(fn(User $record) => $record->auditLogs()->where('action', 'login')->latest()->first()?->created_at?->diffForHumans() ?? 'Never'),
                                    ])->columns(2),
                            ]),

                        // Right Column
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Products/Services')
                                    ->schema([
                                        TextEntry::make('services_count')
                                            ->label('Services')
                                            ->inlineLabel()
                                            ->state(fn(User $record) => $record->verificationOrders()->count()),
                                        TextEntry::make('domains_count')
                                            ->label('Domains')
                                            ->default('0')
                                            ->inlineLabel(),
                                        TextEntry::make('tickets_count')
                                            ->label('Support Tickets')
                                            ->inlineLabel()
                                            ->state(fn(User $record) => $record->supportTickets()->count()),
                                        TextEntry::make('affiliate_signups')
                                            ->label('Affiliate Signups')
                                            ->default('0')
                                            ->inlineLabel(),
                                    ]),

                                Section::make('Files')
                                    ->schema([
                                        TextEntry::make('files_count')->label('No files uploaded')->default('No files uploaded')->hiddenLabel(),
                                    ]),

                                Section::make('Recent Emails')
                                    ->schema([
                                        TextEntry::make('emails_count')->label('No recent emails')->default('No recent emails')->hiddenLabel(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
