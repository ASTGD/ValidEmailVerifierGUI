<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public SupportMessage $supportMessage,
        public bool $isAdminReply
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isAdminReply
            ? "New Reply on Ticket #{$this->ticket->ticket_number}"
            : "Customer Replied to Ticket #{$this->ticket->ticket_number}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket-notification');
    }
}
