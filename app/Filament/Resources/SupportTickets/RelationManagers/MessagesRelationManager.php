<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Models\SupportMessage;
use App\Enums\SupportTicketStatus;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
// Correct action imports for your project structure
use Filament\Actions\CreateAction;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $recordTitleAttribute = 'content';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Textarea::make('content')
                ->label('Reply to Customer')
                ->placeholder('Describe the solution or ask for more details...')
                ->required()
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('attachment')
                ->image()
                ->directory('support-attachments')
                ->disk('public'),

            Forms\Components\Hidden::make('user_id')
                ->default(auth()->id()),

            Forms\Components\Hidden::make('is_admin')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Conversation Thread')
            // NEWEST FIRST: Last reply at the top
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->weight('bold')
                    ->color(fn($record) => $record->is_admin ? 'primary' : 'gray')
                    ->description(fn($record) => $record->is_admin ? 'STAFF REPLY' : 'CUSTOMER MESSAGE'),

                Tables\Columns\TextColumn::make('content')
                    ->label('Message Content')
                    ->wrap()
                    ->grow(),

                Tables\Columns\ImageColumn::make('attachment')
                    ->label('File')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->size(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->alignment('right')
                    ->color('gray')
                    ->description(fn($record) => $record->created_at->format('M d, Y H:i')),
            ])
            ->headerActions([
                // Using the corrected Action class
                CreateAction::make()
                    ->label('Post New Reply')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->modalHeading('Reply to Customer')
                    ->modalButton('Send Message')
                    ->after(fn(SupportMessage $record) => $record->ticket->update([
                        'status' => SupportTicketStatus::Pending
                    ])),
            ]);
    }
    public function isCollapsible(): bool
    {
        return true;
    }

    // Optional: Starts the page with this section closed
    public function isCollapsedByDefault(): bool
    {
        return true;
    }
}
