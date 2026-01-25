<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\User;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;


class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpan('full')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Summary')
                            ->icon('heroicon-m-user-circle')
                            ->schema([
                                Grid::make(3)
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
                            ]),

                        // Tab::make('Profile')
                        //     ->schema([
                        //         Section::make('Profile Details')
                        //             ->schema([
                        //                 TextEntry::make('name'),
                        //                 TextEntry::make('email'),
                        //                 // Specific profile fields repeated if needed for editing context visual
                        //                 TextEntry::make('marketing_emails_opt_in')->label('Marketing Emails'),
                        //             ])->columns(2),
                        //     ]),

                        Tab::make('Products/Services')
                            ->icon('heroicon-m-cube')
                            ->badge(fn($record) => $record->verificationOrders()->count())
                            ->schema([
                                Grid::make(9)
                                    ->extraAttributes(['class' => 'mt-6'])
                                    ->schema([
                                        TextEntry::make('h_id')->label('ID')->hiddenLabel()->default('ID')->weight('bold'),
                                        TextEntry::make('h_order_number')->label('Order Number')->hiddenLabel()->default('Order Number')->weight('bold'),
                                        TextEntry::make('h_date')->label('Date')->hiddenLabel()->default('Date')->weight('bold'),
                                        TextEntry::make('h_client_name')->label('Client Name')->hiddenLabel()->default('Client Name')->weight('bold'),
                                        TextEntry::make('h_payment_method')->label('Payment Method')->hiddenLabel()->default('Payment Method')->weight('bold'),
                                        TextEntry::make('h_amount')->label('Amount')->hiddenLabel()->default('Amount')->weight('bold'),
                                        TextEntry::make('h_payment_status')->label('Payment Status')->hiddenLabel()->default('Payment Status')->weight('bold'),
                                        TextEntry::make('h_order_status')->label('Order Status')->hiddenLabel()->default('Order Status')->weight('bold'),
                                        TextEntry::make('h_actions')->label('Actions')->hiddenLabel()->default('Actions')->weight('bold'),
                                    ])
                                    ->visible(fn($record) => $record->verificationOrders()->exists()),

                                \Filament\Infolists\Components\RepeatableEntry::make('verificationOrders')
                                    ->label('Verification Orders')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(9)
                                            ->schema([
                                                TextEntry::make('id')->label('ID')->hiddenLabel(),
                                                TextEntry::make('order_number')->label('Order Number')->hiddenLabel()->weight('bold'),
                                                TextEntry::make('created_at')->label('Date')->hiddenLabel()->dateTime()->color('gray'),
                                                TextEntry::make('client_name')
                                                    ->label('Client Name')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => $record->user->name),
                                                TextEntry::make('payment_method')
                                                    ->label('Payment Method')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => $record->paymentMethodLabel()),
                                                TextEntry::make('amount')
                                                    ->label('Amount')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => $record->currency . ' ' . number_format($record->amount_cents / 100, 2)),
                                                TextEntry::make('payment_status')
                                                    ->label('Payment Status')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->state(fn($record) => $record->paymentStatusLabel())
                                                    ->color(fn($record) => match ($record->paymentStatusKey()) {
                                                        'paid' => 'success',
                                                        'failed' => 'danger',
                                                        'refunded' => 'warning',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('status')
                                                    ->label('Order Status')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color(fn($state) => match ($state instanceof \App\Enums\VerificationOrderStatus ? $state : \App\Enums\VerificationOrderStatus::tryFrom($state)) {
                                                        \App\Enums\VerificationOrderStatus::Delivered => 'success',
                                                        \App\Enums\VerificationOrderStatus::Processing => 'info',
                                                        \App\Enums\VerificationOrderStatus::Pending => 'warning',
                                                        \App\Enums\VerificationOrderStatus::Failed, \App\Enums\VerificationOrderStatus::Fraud => 'danger',
                                                        default => 'gray',
                                                    }),
                                                \Filament\Schemas\Components\Actions::make([
                                                    \Filament\Actions\Action::make('viewOrder')
                                                        ->label('View')
                                                        ->icon('heroicon-m-eye')
                                                        ->url(fn($record) => \App\Filament\Resources\VerificationOrders\VerificationOrderResource::getUrl('view', ['record' => $record]))
                                                        ->color('gray'),
                                                ]),
                                            ]),
                                    ])
                                    ->visible(fn($record) => $record->verificationOrders()->exists()),

                                TextEntry::make('no_orders')
                                    ->default('No verification orders found.')
                                    ->hiddenLabel()
                                    ->visible(fn($record) => !$record->verificationOrders()->exists()),
                            ]),

                        Tab::make('Invoices')
                            ->icon('heroicon-m-banknotes')
                            ->badge(fn($record) => $record->verificationOrders()->count())
                            ->schema([
                                Grid::make(8)
                                    ->extraAttributes(['class' => 'mt-6'])
                                    ->schema([
                                        TextEntry::make('h_inv_num')->label('Invoice #')->hiddenLabel()->default('Invoice #')->weight('bold'),
                                        TextEntry::make('h_inv_date')->label('Invoice Date')->hiddenLabel()->default('Invoice Date')->weight('bold'),
                                        TextEntry::make('h_due_date')->label('Due Date')->hiddenLabel()->default('Due Date')->weight('bold'),
                                        TextEntry::make('h_date_paid')->label('Date Paid')->hiddenLabel()->default('Date Paid')->weight('bold'),
                                        TextEntry::make('h_total')->label('Total')->hiddenLabel()->default('Total')->weight('bold'),
                                        TextEntry::make('h_pay_method')->label('Payment Method')->hiddenLabel()->default('Payment Method')->weight('bold'),
                                        TextEntry::make('h_status')->label('Status')->hiddenLabel()->default('Status')->weight('bold'),
                                        TextEntry::make('h_actions')->label('Actions')->hiddenLabel()->default('Actions')->weight('bold'),
                                    ])
                                    ->visible(fn($record) => $record->verificationOrders()->exists()),

                                \Filament\Infolists\Components\RepeatableEntry::make('verificationOrders')
                                    ->label('Invoices')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(8)
                                            ->schema([
                                                TextEntry::make('order_number')->label('Invoice #')->hiddenLabel()->weight('bold'),
                                                TextEntry::make('created_at')->label('Invoice Date')->hiddenLabel()->date(),
                                                TextEntry::make('created_at')->label('Due Date')->hiddenLabel()->date()->color('gray'), // Placeholder
                                                TextEntry::make('refunded_at')->label('Date Paid')->hiddenLabel()->date()->placeholder('-'),
                                                TextEntry::make('amount')
                                                    ->label('Total')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => $record->currency . ' ' . number_format($record->amount_cents / 100, 2)),
                                                TextEntry::make('payment_method')
                                                    ->label('Payment Method')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => $record->paymentMethodLabel()),
                                                TextEntry::make('payment_status')
                                                    ->label('Status')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->state(fn($record) => $record->paymentStatusLabel())
                                                    ->color(fn($record) => match ($record->paymentStatusKey()) {
                                                        'paid' => 'success',
                                                        'failed' => 'danger',
                                                        'refunded' => 'warning',
                                                        default => 'gray',
                                                    }),
                                                \Filament\Schemas\Components\Actions::make([
                                                    \Filament\Actions\Action::make('viewInvoice')
                                                        ->label('View')
                                                        ->icon('heroicon-m-eye')
                                                        ->url(fn($record) => \App\Filament\Resources\VerificationOrders\VerificationOrderResource::getUrl('view', ['record' => $record]))
                                                        ->color('gray'),
                                                ]),
                                            ]),
                                    ])
                                    ->visible(fn($record) => $record->verificationOrders()->exists()),

                                TextEntry::make('no_invoices')
                                    ->default('No invoices found.')
                                    ->hiddenLabel()
                                    ->visible(fn($record) => !$record->verificationOrders()->exists()),
                            ]),

                        Tab::make('Tickets')
                            ->icon('heroicon-m-ticket')
                            ->badge(fn($record) => $record->supportTickets()->count())
                            ->schema([
                                Grid::make(6)
                                    ->extraAttributes(['class' => 'mt-6'])
                                    ->schema([
                                        TextEntry::make('h_date_open')->label('Date Opened')->hiddenLabel()->default('Date Opened')->weight('bold'),
                                        TextEntry::make('h_dept')->label('Department')->hiddenLabel()->default('Department')->weight('bold'),
                                        TextEntry::make('h_subject')->label('Subject')->hiddenLabel()->default('Subject')->weight('bold'),
                                        TextEntry::make('h_ticket_status')->label('Status')->hiddenLabel()->default('Status')->weight('bold'),
                                        TextEntry::make('h_last_reply')->label('Last Reply')->hiddenLabel()->default('Last Reply')->weight('bold'),
                                        TextEntry::make('h_actions')->label('Actions')->hiddenLabel()->default('Actions')->weight('bold'),
                                    ])
                                    ->visible(fn($record) => $record->supportTickets()->exists()),

                                \Filament\Infolists\Components\RepeatableEntry::make('supportTickets')
                                    ->label('Tickets')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                TextEntry::make('created_at')->label('Date Opened')->hiddenLabel()->dateTime(),
                                                TextEntry::make('category')->label('Department')->hiddenLabel(),
                                                TextEntry::make('subject')->label('Subject')
                                                    ->hiddenLabel()
                                                    ->state(fn($record) => "#{$record->ticket_number} - {$record->subject}"),
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color(fn($state) => match ($state instanceof \App\Enums\SupportTicketStatus ? $state : \App\Enums\SupportTicketStatus::tryFrom($state)) {
                                                        \App\Enums\SupportTicketStatus::Open => 'success',
                                                        \App\Enums\SupportTicketStatus::Closed => 'gray',
                                                        \App\Enums\SupportTicketStatus::InProgress => 'info',
                                                        \App\Enums\SupportTicketStatus::OnHold => 'warning',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('updated_at')->label('Last Reply')->hiddenLabel()->since(),
                                                \Filament\Schemas\Components\Actions::make([
                                                    \Filament\Actions\Action::make('viewTicket')
                                                        ->label('View')
                                                        ->icon('heroicon-m-eye')
                                                        ->url(fn($record) => \App\Filament\Resources\SupportTickets\SupportTicketResource::getUrl('view', ['record' => $record]))
                                                        ->color('gray'),
                                                ]),
                                            ]),
                                    ])
                                    ->visible(fn($record) => $record->supportTickets()->exists()),

                                TextEntry::make('no_tickets')
                                    ->default('No tickets found.')
                                    ->hiddenLabel()
                                    ->visible(fn($record) => !$record->supportTickets()->exists()),
                            ]),

                        Tab::make('Log')
                            ->icon('heroicon-m-clipboard-document-list')
                            ->badge(fn($record) => $record->auditLogs()->count())
                            ->schema([
                                Grid::make(4)
                                    ->extraAttributes(['class' => 'mt-6'])
                                    ->schema([
                                        TextEntry::make('h_date')->label('Date')->hiddenLabel()->default('Date')->weight('bold'),
                                        TextEntry::make('h_log')->label('Log Entry')->hiddenLabel()->default('Log Entry')->weight('bold'),
                                        TextEntry::make('h_user')->label('User')->hiddenLabel()->default('User')->weight('bold'),
                                        TextEntry::make('h_ip')->label('IP Address')->hiddenLabel()->default('IP Address')->weight('bold'),
                                    ])
                                    ->visible(fn($record) => $record->auditLogs()->exists()),

                                \Filament\Infolists\Components\RepeatableEntry::make('auditLogs')
                                    ->label('Logs')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('created_at')->label('Date')->hiddenLabel()->dateTime(),
                                                TextEntry::make('action')->label('Log Entry')->hiddenLabel(),
                                                TextEntry::make('user.name')->label('User')->hiddenLabel()->default('System/Automated'),
                                                TextEntry::make('ip_address')->label('IP Address')->hiddenLabel()->placeholder('-'),
                                            ]),
                                    ])
                                    ->visible(fn($record) => $record->auditLogs()->exists()),

                                TextEntry::make('no_logs')
                                    ->default('No logs found.')
                                    ->hiddenLabel()
                                    ->visible(fn($record) => !$record->auditLogs()->exists()),
                            ]),
                    ])
                    ->persistTabInQueryString()
            ]);
    }
}
