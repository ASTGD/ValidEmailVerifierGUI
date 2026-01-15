<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportMessage;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    /**
     * This runs after the ticket is created in the database.
     */
    protected function afterCreate(): void
    {
        $ticket = $this->record;

        // Get the message from the form data
        $messageContent = $this->data['message'];

        // Save it to the messages table
        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'content' => $messageContent,
            'is_admin' => true,
        ]);
    }
}
