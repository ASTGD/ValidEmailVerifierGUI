<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Client Information')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('First Name')
                                            ->required(),
                                        TextInput::make('last_name')
                                            ->label('Last Name')
                                            ->required(),
                                    ]),
                                TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->placeholder('(Optional)'),
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                                    ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                                    ->suffixAction(
                                        Action::make('generatePassword')
                                            ->icon('heroicon-o-key')
                                            ->action(function (Set $set) {
                                                $set('password', Str::random(16));
                                            })
                                    ),
                                Select::make('language')
                                    ->label('Language')
                                    ->options(['en' => 'Default (English)', 'es' => 'Spanish', 'fr' => 'French'])
                                    ->default('en'),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'closed' => 'Closed',
                                    ])
                                    ->default('active')
                                    ->required(),
                                Select::make('client_group')
                                    ->label('Client Group')
                                    ->options([
                                        'none' => 'None',
                                        'vip' => 'VIP',
                                        'reseller' => 'Reseller',
                                    ])
                                    ->default('none'),
                            ]),

                        Section::make('Address & Billing')
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('address_1')
                                    ->label('Address 1'),
                                TextInput::make('address_2')
                                    ->label('Address 2')
                                    ->placeholder('(Optional)'),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('city')
                                            ->label('City'),
                                        TextInput::make('state')
                                            ->label('State/Region'),
                                        TextInput::make('postcode')
                                            ->label('Postcode'),
                                        Select::make('country')
                                            ->label('Country')
                                            ->options([
                                                'US' => 'United States',
                                                'UK' => 'United Kingdom',
                                                'CA' => 'Canada',
                                                // Add more as needed
                                            ])
                                            ->default('US')
                                            ->searchable(),
                                    ]),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel(),
                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'default' => 'Select to Change Default',
                                        'card' => 'Credit Card',
                                        'paypal' => 'PayPal',
                                    ])
                                    ->default('default'),
                                Select::make('billing_contact')
                                    ->label('Billing Contact')
                                    ->options(['default' => 'Default'])
                                    ->default('default'),
                                Select::make('currency')
                                    ->label('Currency')
                                    ->options(['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'])
                                    ->default('USD'),
                            ]),
                    ]),

                Section::make('Email Notifications')
                    ->description('Manage email notification preferences')
                    // ->collapsible()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Checkbox::make('notify_general')->label('General Emails - All account related emails')->default(true),
                                Checkbox::make('notify_invoice')->label('Invoice Emails - New Invoices, Reminders, & Overdue Notices')->default(true),
                                Checkbox::make('notify_support')->label('Support Emails - Receive a copy of all Support Ticket Communications')->default(true),
                                Checkbox::make('notify_product')->label('Product Emails - Welcome Emails, Suspensions & Other Lifecycle Notifications')->default(true),
                                Checkbox::make('notify_domain')->label('Domain Emails - Registration/Transfer Confirmation & Renewal Notices')->default(true),
                                Checkbox::make('notify_affiliate')->label('Affiliate Emails - Receive Affiliate Notifications')->default(true),
                            ])
                    ]),

                Section::make('Settings')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('allow_late_fees')->label('Late Fees')->default(true),
                                Toggle::make('send_overdue_notices')->label('Overdue Notices')->default(true),
                                Toggle::make('tax_exempt')->label('Tax Exempt'),
                                Toggle::make('separate_invoices')->label('Separate Invoices'),
                                Toggle::make('disable_cc_processing')->label('Disable CC Processing'),
                                Toggle::make('marketing_emails_opt_in')->label('Marketing Emails Opt-in'),
                                Toggle::make('status_update_enabled')->label('Status Update')->default(true),
                                Toggle::make('allow_sso')->label('Allow Single Sign-On')->default(true),
                            ])
                    ]),

                Section::make('Admin Notes')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Checkbox::make('send_welcome_email')
                    ->label('Check to send a New Account Information Message')
                    ->dehydrated(false),
            ]);
    }
}
