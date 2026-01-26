<?php

namespace App\Filament\Resources\Customers\Schemas;

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
                                        TextEntry::make('first_name')->label('First Name'),
                                        TextEntry::make('last_name')->label('Last Name'),
                                        TextEntry::make('company_name')->label('Company Name'),
                                        TextEntry::make('email')->label('Email Address')->copyable(),
                                        TextEntry::make('address_1')->label('Address 1'),
                                        TextEntry::make('address_2')->label('Address 2'),
                                        TextEntry::make('city')->label('City'),
                                        TextEntry::make('state')->label('State/Region'),
                                        TextEntry::make('postcode')->label('Postcode'),
                                        TextEntry::make('country')->label('Country'),
                                        TextEntry::make('phone')->label('Phone Number'),
                                    ])->columns(2),

                                Section::make('Contacts')
                                    ->schema([
                                        TextEntry::make('contacts_count')
                                            ->label('No additional contacts setup')
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
                                        TextEntry::make('paid_invoices')->label('Paid')->placeholder('0 (0.00 USD)'),
                                        TextEntry::make('unpaid_invoices')->label('Unpaid')->placeholder('0 (0.00 USD)'),
                                        TextEntry::make('cancelled_invoices')->label('Cancelled')->placeholder('0 (0.00 USD)'),
                                        TextEntry::make('refunded_invoices')->label('Refunded')->placeholder('0 (0.00 USD)'),
                                        TextEntry::make('collections_invoices')->label('Collections')->placeholder('0 (0.00 USD)'),
                                        TextEntry::make('income')->label('Income')->placeholder('0.00 USD'),
                                        TextEntry::make('credit_balance')->label('Credit Balance')->placeholder('0.00 USD'),
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
                                        TextEntry::make('last_login')->label('Last Login')->placeholder('Never'),
                                    ])->columns(2),
                            ]),

                        // Right Column
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make('Products/Services')
                                    ->schema([
                                        TextEntry::make('verification_jobs_count')->label('Services')->inlineLabel(),
                                        // Placeholders for other counts
                                        TextEntry::make('domains_count')->label('Domains')->default('0')->inlineLabel(),
                                        TextEntry::make('tickets_count')->label('Support Tickets')->default('0')->inlineLabel(),
                                        TextEntry::make('affiliate_signups')->label('Affiliate Signups')->default('0')->inlineLabel(),
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
