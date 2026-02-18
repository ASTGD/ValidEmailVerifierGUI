<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $action // 'updated', 'closed', 'deleted'
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->action === 'deleted' ? 'Deleted' : $this->ticket->status->label();

        return new Envelope(
            subject: "[Ticket #{$this->ticket->ticket_number}] Status Update: {$status}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-status',
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:default',
            'mail:ticket_status',
            'ticket:'.$this->ticket->id,
            'ticket_action:'.$this->action,
        ];
    }
}
