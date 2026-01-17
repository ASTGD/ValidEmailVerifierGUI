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
                            ->color('primary')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => match ($state->value) {
                                'open' => 'info',
                                'pending' => 'warning',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray'
                            }),
                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => match ($state->value) {
                                'Urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                default => 'gray'
                            }),
                        TextEntry::make('category')
                            ->label('Department')
                            ->weight('bold'),
                    ])->columns(4),

                Section::make('Customer Context')
                    ->schema([
                        TextEntry::make('user.name')->label('Account Name')->icon('heroicon-m-user'),
                        TextEntry::make('user.email')->label('Email Address')->icon('heroicon-m-envelope')->copyable(),
                        TextEntry::make('created_at')->label('Date Opened')->dateTime('M d, Y (H:i)'),
                        TextEntry::make('updated_at')->label('Last Activity')->since(),
                    ])->columns(4),

                // Section::make('Initial Customer Request')
                //     ->collapsed() // Keeps page clean
                //     ->schema([
                //         TextEntry::make('subject')->weight('bold')->size('lg'),
                //         TextEntry::make('message')->label('Original Content')->prose()->markdown(),
                //     ]),
            ]);
    }
}
