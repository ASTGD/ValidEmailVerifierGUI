<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Support\Roles;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket')
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'email', modifyQueryUsing: fn ($query) => $query->role(Roles::CUSTOMER))
                            ->searchable()
                            ->required(),
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('message')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Workflow')
                    ->schema([
                        Select::make('status')
                            ->options(self::statusOptions())
                            ->default(SupportTicketStatus::Open->value)
                            ->required()
                            ->native(false),
                        Select::make('priority')
                            ->options(self::priorityOptions())
                            ->default(SupportTicketPriority::Normal->value)
                            ->native(false),
                        Select::make('assigned_to')
                            ->label('Assigned to')
                            ->relationship('assignedTo', 'email', modifyQueryUsing: fn ($query) => $query->role(Roles::ADMIN))
                            ->searchable(),
                    ])
                    ->columns(3),
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
