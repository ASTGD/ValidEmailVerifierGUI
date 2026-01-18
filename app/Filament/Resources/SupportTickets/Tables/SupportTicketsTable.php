<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // ADDED AS THE FIRST COLUMN
                TextColumn::make('ticket_number')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono') // Industry standard for IDs
                    ->weight('bold')
                    ->color('primary'), // Matches your brand blue

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('user.email')
                    ->label('Requestor')
                    ->searchable(),

                TextColumn::make('status')
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

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if (empty($state)) return '-';
                        if ($state instanceof SupportTicketPriority) return $state->label();
                        return ucfirst((string) $state);
                    })
                    ->color(function ($state): string {
                        if (empty($state)) return 'gray';
                        $value = $state instanceof SupportTicketPriority ? $state->value : (string) $state;
                        return match ($value) {
                            SupportTicketPriority::Urgent->value => 'danger',
                            SupportTicketPriority::High->value => 'warning',
                            SupportTicketPriority::Normal->value => 'info',
                            SupportTicketPriority::Low->value => 'gray',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('assignedTo.email')
                    ->label('Assigned to')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(self::priorityOptions()),
            ])
            ->emptyStateHeading('No support tickets yet')
            ->emptyStateDescription('Tickets will appear here when customers contact support.')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    private static function statusOptions(): array
    {
        $options = [];
        foreach (SupportTicketStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }
        return $options;
    }

    private static function priorityOptions(): array
    {
        $options = [];
        foreach (SupportTicketPriority::cases() as $priority) {
            $options[$priority->value] = $priority->label();
        }
        return $options;
    }
}
