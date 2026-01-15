<?php

namespace App\Livewire\Portal;

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketPriority;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
class Support extends Component
{
    use WithFileUploads, WithPagination;

    // Form properties
    public string $subject = '';
    public string $message = '';
    public string $category = 'Technical';
    public string $priority = 'normal';
    public $attachment;

    public function render()
    {
        return view('livewire.portal.support', [
            'supportEmail' => config('support.email'),
            'supportUrl' => config('support.url'),
            'tickets' => SupportTicket::where('user_id', Auth::id())
                ->latest()
                ->paginate(10),
        ]);
    }

    /**
     * Create a new support ticket and the first message.
     */
    public function createTicket()
    {
        $this->validate([
            'subject' => 'required|min:5|max:255',
            'message' => 'required|min:10',
            'category' => 'required',
            'priority' => 'required',
            'attachment' => 'nullable|image|max:2048',
        ]);

        // 1. Create the Ticket Header
        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $this->subject,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => SupportTicketStatus::Open,
        ]);

        // 2. Create the first message in the chat
        $path = $this->attachment ? $this->attachment->store('support-attachments', 'public') : null;

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'content' => $this->message,
            'attachment' => $path,
            'is_admin' => false,
        ]);

        // Reset form and notify user
        $this->reset(['subject', 'message', 'attachment']);
        session()->flash('status', 'Ticket created successfully.');

        return redirect()->route('portal.support.show', $ticket);
    }
}
