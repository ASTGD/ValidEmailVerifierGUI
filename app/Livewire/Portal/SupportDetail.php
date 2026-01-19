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
        $this->ticket = $ticket;
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

        // Update status to Customer-Reply so Admin sees the customer replied
        $this->ticket->update(['status' => SupportTicketStatus::CustomerReply]);

        $this->reset(['message', 'attachment']);
        $this->ticket->refresh();
    }

    public function render()
    {
        return view('livewire.portal.support-detail', [
            'messages' => $this->ticket->messages()->oldest()->get()
        ])->layout('layouts.portal'); // <--- This line is the fix
    }
}
