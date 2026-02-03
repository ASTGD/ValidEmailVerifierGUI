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
                    \Filament\Forms\Components\Select::make('status')
                        ->label('Update Ticket Status')
                        ->options(SupportTicketStatus::class)
                        ->default(fn() => $this->record->status)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->messages()->create([
                        'user_id' => auth()->id(),
                        'content' => $data['content'],
                        'attachment' => $data['attachment'] ?? null,
                        'is_admin' => true,
                    ]);

                    $this->record->update(['status' => $data['status']]);

                    \App\Support\AdminAuditLogger::log('ticket_reply_sent', $this->record);
                }),

            // MARK AS RESOLVED
            Action::make('resolve')
                ->label('Mark Resolved')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => SupportTicketStatus::Resolved]);
                    \App\Support\AdminAuditLogger::log('ticket_resolved', $this->record);
                })
                ->visible(fn() => $this->record->status !== SupportTicketStatus::Resolved),

            // CLOSE TICKET
            Action::make('close')
                ->label('Close Ticket')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => SupportTicketStatus::Closed]);
                    \App\Support\AdminAuditLogger::log('ticket_closed', $this->record);
                })
                ->visible(fn() => $this->record->status !== SupportTicketStatus::Closed),

        ];
    }
}
