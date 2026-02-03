<?php

namespace App\Livewire\Portal;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Enums\SupportTicketStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class SupportDetail extends Component
{
    use WithFileUploads;

    public SupportTicket $ticket;
    public string $message = '';
    public $attachment;

    public function mount(SupportTicket $ticket)
    {
        // Security: Ensure user only sees their own ticket
        if ($ticket->user_id !== Auth::id()) {
            abort(403);
        }
        $this->ticket = $ticket->load('order');
    }

    public function sendMessage()
    {
        $this->validate([
            'message' => 'required|min:2',
            'attachment' => 'nullable|image|max:2048',
        ]);

        $path = $this->attachment ? $this->attachment->store('support-attachments', 'public') : null;

        SupportMessage::create([
            'support_ticket_id' => $this->ticket->id,
            'user_id' => Auth::id(),
            'content' => $this->message,
            'attachment' => $path,
            'is_admin' => false,
        ]);

        $this->ticket->update(['status' => SupportTicketStatus::CustomerReply]);

        \App\Support\AdminAuditLogger::log('ticket_reply_sent', $this->ticket);

        $this->reset(['message', 'attachment']);
        $this->ticket->refresh();
    }

    public function render()
    {
        return view('livewire.portal.support-detail', [
            'messages' => $this->ticket->messages()->oldest()->get()
        ])->layout('layouts.portal'); // <--- This line is the fix
    }

    public function closeTicket()
    {
        // 1. Security check: Ensure the user owns this ticket
        if ($this->ticket->user_id !== \Illuminate\Support\Facades\Auth::id()) {
            abort(403);
        }

        $this->ticket->update([
            'status' => \App\Enums\SupportTicketStatus::Closed
        ]);

        \App\Support\AdminAuditLogger::log('ticket_closed', $this->ticket);

        // 3. Optional: Add a log or notification
        session()->flash('status', 'Ticket has been marked as Closed.');

        // 4. Refresh the page to update the UI
        $this->ticket->refresh();
    }
}
