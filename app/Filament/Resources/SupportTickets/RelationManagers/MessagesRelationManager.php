<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Models\SupportMessage;
use App\Enums\SupportTicketStatus;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
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
                ->placeholder('Write your reply here...')
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
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sender')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('content')
                    ->label('Message')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin Reply'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent Date')
                    ->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Send New Reply')
                    ->modalHeading('Send Reply to Customer')
                    ->after(fn(SupportMessage $record) => $record->ticket->update([
                        'status' => SupportTicketStatus::Pending
                    ])),
            ]);
    }
}
