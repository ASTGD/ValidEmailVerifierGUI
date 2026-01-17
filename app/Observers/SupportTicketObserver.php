<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Mail\NewTicketNotification;
use App\Mail\TicketStatusNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class SupportTicketObserver
{
    /**
     * Handle the SupportTicket "created" event.
     */
    public function created(SupportTicket $supportTicket): void
    {
        // Logic handled in SupportMessageObserver to ensure message content is included
    }

    /**
     * Handle the SupportTicket "updated" event.
     */
    public function updated(SupportTicket $supportTicket): void
    {
        // Check if status changed
        if ($supportTicket->isDirty('status')) {
            $user = $supportTicket->user;

            // Notify User about status update
            // (Even if User changed it themselves, a confirmation is okay, but usually we notify the 'other' party)
            // Ideally: If Admin changed it -> Notify User. If User changed it -> Notify Admin.

            if (Auth::id() === $supportTicket->user_id) {
                // User changed something (e.g. Closed ticket) -> Notify Admin
                $adminEmail = config('support.admin_email') ?? config('mail.from.address');
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new TicketStatusNotification($supportTicket, 'updated'));
                }
            } else {
                // Admin/System changed it -> Notify User
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new TicketStatusNotification($supportTicket, 'updated'));
                }
            }
        }
    }

    /**
     * Handle the SupportTicket "deleted" event.
     */
    public function deleted(SupportTicket $supportTicket): void
    {
        $user = $supportTicket->user;
        if ($user && $user->email) {
            Mail::to($user->email)->send(new TicketStatusNotification($supportTicket, 'deleted'));
        }
    }
}
