<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Filament\Schemas\Schema;
// Layout: Using the exact namespace from your working Infolist
use Filament\Schemas\Components\Section;
// Data Fields: Using standard Filament 3 namespaces
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket Details')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->label('Customer')
                            ->searchable()
                            ->required(),
                        TextInput::make('subject')
                            ->required(),
                        Select::make('category')
                            ->options([
                                'Technical' => 'Technical',
                                'Billing' => 'Billing',
                                'Sales' => 'Sales',
                            ])->required(),
                        Select::make('priority')
                            ->options(SupportTicketPriority::class)
                            ->required(),

                        // We use dehydrated(false) as discussed to handle this via SupportMessage
                        Textarea::make('message')
                            ->label('Initial Message')
                            ->required()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options(SupportTicketStatus::class)
                            ->default(SupportTicketStatus::Open)
                            ->required(),
                    ])->columns(2)
            ]);
    }
}
