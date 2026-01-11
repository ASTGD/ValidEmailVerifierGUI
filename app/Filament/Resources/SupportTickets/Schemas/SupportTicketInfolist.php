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
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Customer')
                            ->copyable(),
                        TextEntry::make('assignedTo.email')
                            ->label('Assigned to')
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Ticket')
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Subject'),
                        TextEntry::make('message')
                            ->label('Message')
                            ->prose(),
                    ])
                    ->columns(1),
                Section::make('Workflow')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(function ($state): string {
                                if ($state instanceof SupportTicketStatus) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(function ($state): string {
                                $value = $state instanceof SupportTicketStatus ? $state->value : (string) $state;

                                return match ($value) {
                                    SupportTicketStatus::Open->value => 'info',
                                    SupportTicketStatus::Pending->value => 'warning',
                                    SupportTicketStatus::Closed->value => 'success',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('priority')
                            ->label('Priority')
                            ->badge()
                            ->formatStateUsing(function ($state): string {
                                if (empty($state)) {
                                    return '-';
                                }

                                if ($state instanceof SupportTicketPriority) {
                                    return $state->label();
                                }

                                return ucfirst((string) $state);
                            })
                            ->color(function ($state): string {
                                if (empty($state)) {
                                    return 'gray';
                                }

                                $value = $state instanceof SupportTicketPriority ? $state->value : (string) $state;

                                return match ($value) {
                                    SupportTicketPriority::High->value => 'danger',
                                    SupportTicketPriority::Normal->value => 'info',
                                    SupportTicketPriority::Low->value => 'gray',
                                    default => 'gray',
                                };
                            }),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('closed_at')
                            ->label('Closed')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
