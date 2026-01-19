<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket Overview')
                    ->description('Primary details and current status of the request.')
                    ->schema([
                        TextEntry::make('ticket_number')
                            ->label('Ticket ID')
                            ->fontFamily('mono')
                            ->weight('black')
                            ->color('warning') // Orange/Amber as per user screenshot
                            ->icon('heroicon-m-hashtag')
                            ->copyable(),

                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state->label())
                            ->color(fn($state) => match ($state->value) {
                                'open' => 'info',
                                'pending' => 'warning',
                                'answered' => 'success',
                                'customer_reply' => 'danger',
                                'on_hold' => 'gray',
                                'in_progress' => 'primary',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray'
                            })
                            ->icon(fn($state) => match ($state->value) {
                                'open' => 'heroicon-m-bolt',
                                'pending' => 'heroicon-m-clock',
                                'answered' => 'heroicon-m-chat-bubble-left-right',
                                'customer_reply' => 'heroicon-m-exclamation-circle',
                                'on_hold' => 'heroicon-m-pause-circle',
                                'in_progress' => 'heroicon-m-arrow-path',
                                'resolved' => 'heroicon-m-check-badge',
                                'closed' => 'heroicon-m-x-circle',
                                default => 'heroicon-m-information-circle'
                            }),

                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state->label())
                            ->color(fn($state) => match ($state->value) {
                                'Urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                'low' => 'gray',
                                default => 'gray'
                            })
                            ->icon(fn($state) => match ($state->value) {
                                'Urgent' => 'heroicon-m-fire',
                                'high' => 'heroicon-m-chevron-double-up',
                                'normal' => 'heroicon-m-chevron-up',
                                default => 'heroicon-m-chevron-down'
                            }),

                        TextEntry::make('category')
                            ->label('Department')
                            ->icon('heroicon-m-tag')
                            ->color('gray')
                            ->weight('black'),
                    ])->columns(4),

                Section::make('Customer Context')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Account Name')
                            ->icon('heroicon-m-user')
                            ->url(fn($record) => route('filament.admin.resources.customers.index', [
                                'tableSearch' => $record->user->email,
                            ]))
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->weight('bold'),
                        TextEntry::make('user.email')->label('Email Address')->icon('heroicon-m-envelope')->copyable(),
                        TextEntry::make('created_at')->label('Date Opened')->dateTime('M d, Y (H:i)'),
                        TextEntry::make('updated_at')->label('Last Activity')->since(),
                    ])->columns(4),
                Section::make('Ticket Details')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Subject')
                            ->weight('bold')
                            ->size('lg'),

                        // TextEntry::make('message')
                        //     ->label('Initial Message Content')
                        //     ->prose()
                        //     ->markdown()
                        //     ->columnSpanFull(),
                    ])
                    ->columns(1),

                // Section::make('Initial Customer Request')
                //     ->collapsed() // Keeps page clean
                //     ->schema([
                //         TextEntry::make('subject')->weight('bold')->size('lg'),
                //         TextEntry::make('message')->label('Original Content')->prose()->markdown(),
                //     ]),
            ]);
    }
}
