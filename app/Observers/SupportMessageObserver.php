<?php

namespace App\Observers;

use App\Models\SupportMessage;
use App\Mail\TicketMessageNotification;
use Illuminate\Support\Facades\Mail;

class SupportMessageObserver
{
    /**
     * Handle the SupportMessage "created" event.
     */
    public function created(SupportMessage $supportMessage): void
    {
        $ticket = $supportMessage->ticket;
        $user = $ticket->user;
        $adminEmail = config('support.admin_email') ?? config('mail.from.address');

        // Check if this is the first message (New Ticket)
        $messageCount = $ticket->messages()->count();

        if ($messageCount === 1) {
            // New Ticket Notification
            if ($supportMessage->is_admin) {
                // Admin created ticket -> Notify User
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new \App\Mail\NewTicketNotification($ticket, $supportMessage));
                }
            } else {
                // User created ticket -> Notify Admin
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new \App\Mail\NewTicketNotification($ticket, $supportMessage));
                }
            }
        } else {
            // Reply Notification
            if ($supportMessage->is_admin) {
                // Admin replied -> Notify User
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new TicketMessageNotification($ticket, $supportMessage, true));
                }
            } else {
                // User replied -> Notify Admin
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new TicketMessageNotification($ticket, $supportMessage, false));
                }
            }
        }
    }
}
