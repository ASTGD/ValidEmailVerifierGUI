<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Enums\SupportTicketStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable; // Add this import

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    /**
     * This sets the big title at the top of the page
     */
    // public function getHeading(): string | Htmlable
    // {
    //     return $this->record->subject;
    // }

    /**
     * This adds a sub-heading for extra professional detail
     */
    // public function getSubheading(): string | Htmlable | null
    // {
    //     return "Viewing conversation for ticket #" . $this->record->ticket_number;
    // }

    protected function getHeaderActions(): array
    {
        return [
            // QUICK REPLY ACTION
            Action::make('reply')
                ->label('Reply to Ticket')
                ->icon('heroicon-m-chat-bubble-left-right')
                ->color('warning') // Yellow like your screenshot
                ->modalHeading('Send Reply to Customer')
                ->form([
                    \Filament\Forms\Components\Textarea::make('content')
                        ->label('Your Message')
                        ->required()
                        ->rows(6),
                    \Filament\Forms\Components\FileUpload::make('attachment')
                        ->directory('support-attachments')
                        ->image(),
                ])
                ->action(function (array $data) {
                    $this->record->messages()->create([
                        'user_id' => auth()->id(),
                        'content' => $data['content'],
                        'attachment' => $data['attachment'] ?? null,
                        'is_admin' => true,
                    ]);

                    $this->record->update(['status' => SupportTicketStatus::Pending]);
                }),

            // MARK AS RESOLVED
            Action::make('resolve')
                ->label('Mark Resolved')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['status' => SupportTicketStatus::Resolved]))
                ->visible(fn () => $this->record->status !== SupportTicketStatus::Resolved),

            // CLOSE TICKET
            Action::make('close')
                ->label('Close Ticket')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['status' => SupportTicketStatus::Closed]))
                ->visible(fn () => $this->record->status !== SupportTicketStatus::Closed),

            EditAction::make()->label('Edit Details'),
        ];
    }
}
