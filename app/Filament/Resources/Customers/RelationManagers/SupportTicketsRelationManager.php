<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportTickets';

    protected static ?string $title = 'Support Tickets';

    protected static string|\BackedEnum|null $icon = 'heroicon-m-ticket';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->supportTickets()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->color('warning')
                    ->url(fn(SupportTicket $record): string => SupportTicketResource::getUrl('view', ['record' => $record])),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('category')
                    ->label('Department')
                    ->badge()
                    ->color(fn($state): string => match ($state) {
                        'Technical' => 'info',
                        'Billing' => 'warning',
                        'Sales' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('user.email')
                    ->label('Requestor')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof SupportTicketStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn($state) => match ($state instanceof SupportTicketStatus ? $state : SupportTicketStatus::tryFrom((string) $state)) {
                        SupportTicketStatus::Open => 'info',
                        SupportTicketStatus::Pending => 'amber',
                        SupportTicketStatus::Answered => 'success',
                        SupportTicketStatus::CustomerReply => 'danger',
                        SupportTicketStatus::OnHold => 'gray',
                        SupportTicketStatus::InProgress => 'primary',
                        SupportTicketStatus::Resolved => 'success',
                        SupportTicketStatus::Closed => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state instanceof SupportTicketPriority ? $state->label() : ucfirst((string) $state))
                    ->color(fn($state) => match ($state instanceof SupportTicketPriority ? $state : SupportTicketPriority::tryFrom((string) $state)) {
                        SupportTicketPriority::Urgent => 'danger',
                        SupportTicketPriority::High => 'warning',
                        SupportTicketPriority::Normal => 'info',
                        SupportTicketPriority::Low => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('assignedTo.email')
                    ->label('Assigned to'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn() => collect(SupportTicketStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()])->toArray()),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->url(fn($record) => SupportTicketResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
