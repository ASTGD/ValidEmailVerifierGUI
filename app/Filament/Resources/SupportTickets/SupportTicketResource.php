<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketInfolist;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use Filament\Resources\RelationManagers\RelationGroup;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Filament\Actions\Action;
use App\Enums\SupportTicketStatus;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;
    protected static ?string $navigationLabel = 'Support Tickets';
    protected static string|UnitEnum|null $navigationGroup = 'Support';
    protected static ?int $navigationSort = 1;
    protected static ?string $relationsAppearance = 'sections';

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportTicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }
    public static function getRelations(): array
    {
        return [
            // We use a RelationGroup to align the tabs to the left
            RelationGroup::make('Ticket Data', [
                // MESSAGES FIRST
                \App\Filament\Resources\SupportTickets\RelationManagers\MessagesRelationManager::class,
                // ORDERS SECOND
                \App\Filament\Resources\SupportTickets\RelationManagers\UserOrdersRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/create'),
            'view' => ViewSupportTicket::route('/{record}'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
