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
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Textarea::make('content')
                        ->label('Reply to Customer')
                        ->placeholder('Describe the solution or ask for more details...')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('attachment')
                        ->image()
                        ->directory('support-attachments')
                        ->disk('public'),

                    Forms\Components\Select::make('ticket_status')
                        ->label('Update Ticket Status')
                        ->options(SupportTicketStatus::class)
                        ->default(fn($record) => $this->getOwnerRecord()->status)
                        ->selectablePlaceholder(false)
                        ->dehydrated(false) // This field is not for the message model
                        ->required(),
                ]),

            Forms\Components\Hidden::make('user_id')
                ->default(auth()->id()),

            Forms\Components\Hidden::make('is_admin')
                ->default(true),
        ]);
    }
    public function table(Table $table): Table
    {
        // Define your brand's soft blue background
        $adminBg = 'background-color: #f4f8fd !important;';

        return $table
            ->heading('Conversation Thread')
            ->defaultSort('created_at', 'desc')
            ->columns([
                // 1. Author
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->icon('heroicon-m-user')
                    // Link to Customer Admin List filtered by this user
                    ->url(fn($record) => "/admin/customers?tableSearch=" . urlencode($record->user->email))
                    ->openUrlInNewTab()
                    ->color(fn($record) => $record->is_admin ? 'info' : 'warning')
                    ->description(fn($record) => $record->is_admin ? 'ADMIN REPLY' : 'CUSTOMER REPLY')
                    ->extraCellAttributes(fn($record) => [
                        'style' => $record->is_admin ? $adminBg : '',
                    ]),

                // 2. Message Content
                Tables\Columns\TextColumn::make('content')
                    ->label('Message Content')
                    ->wrap()
                    ->grow()
                    ->extraCellAttributes(fn($record) => [
                        'style' => $record->is_admin ? $adminBg : '',
                    ]),

                // 3. File
                Tables\Columns\ImageColumn::make('attachment')
                    ->label('File')
                    ->disk('public')
                    ->size(40)
                    ->placeholder('-')
                    ->extraCellAttributes(fn($record) => [
                        'style' => $record->is_admin ? $adminBg : '',
                    ]),

                // 4. Time
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->alignment('right')
                    ->description(fn($record) => $record->created_at->format('M d, Y H:i'))
                    ->extraCellAttributes(fn($record) => [
                        'style' => $record->is_admin ? $adminBg : '',
                    ]),
            ])
            ->actions([
                // No per-row actions needed for now
            ])
            ->headerActions([
                \Filament\Actions\Action::make('edit_last_message')
                    ->label('Edit Last Chat')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->visible(fn() => $this->getOwnerRecord()->messages()->exists())
                    ->mountUsing(function (\Filament\Schemas\Schema $form) {
                        $lastMessage = $this->getOwnerRecord()->messages()->latest()->first();
                        $form->fill([
                            'content' => $lastMessage?->content,
                        ]);
                    })
                    ->form([
                        Forms\Components\Textarea::make('content')
                            ->label('Correct Message')
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function (array $data) {
                        $lastMessage = $this->getOwnerRecord()->messages()->latest()->first();
                        if ($lastMessage) {
                            $lastMessage->update([
                                'content' => $data['content'],
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Success')
                                ->body('The last message has been updated.')
                                ->success()
                                ->send();
                        }
                    }),

                \Filament\Actions\CreateAction::make()
                    ->label('Post New Reply')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->after(function (array $data) {
                        $this->getOwnerRecord()->update([
                            'status' => $data['ticket_status']
                        ]);
                    }),
            ]);
    }

    // Optional: Starts the page with this section closed
    public function isCollapsedByDefault(): bool
    {
        return true;
    }
}
