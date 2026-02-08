<?php

namespace Tests\Feature;

use App\Jobs\FinalizeVerificationJob;
use App\Jobs\ImportEmailVerificationOutcomesFromCsv;
use App\Jobs\ParseAndChunkJob;
use App\Jobs\PrepareVerificationJob;
use App\Jobs\WriteBackVerificationCacheJob;
use App\Mail\NewTicketNotification;
use App\Mail\TicketMessageNotification;
use App\Mail\TicketStatusNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Tests\TestCase;

class QueueTaggingTest extends TestCase
{
    public function test_verification_pipeline_jobs_expose_operational_tags(): void
    {
        $this->assertContains('lane:prepare', (new PrepareVerificationJob('job-1'))->tags());
        $this->assertContains('lane:parse', (new ParseAndChunkJob('job-1'))->tags());
        $this->assertContains('lane:finalize', (new FinalizeVerificationJob('job-1'))->tags());
        $this->assertContains('lane:imports', (new ImportEmailVerificationOutcomesFromCsv(5))->tags());
        $this->assertContains('lane:cache_writeback', (new WriteBackVerificationCacheJob('job-1'))->tags());
    }

    public function test_queueable_mailables_expose_operational_tags(): void
    {
        $ticket = new SupportTicket;
        $ticket->id = 55;
        $ticket->ticket_number = 'TK-TEST';
        $ticket->subject = 'Test';

        $message = new SupportMessage;
        $message->id = 88;

        $newTicketTags = (new NewTicketNotification($ticket, $message))->tags();
        $statusTags = (new TicketStatusNotification($ticket, 'updated'))->tags();
        $messageTags = (new TicketMessageNotification($ticket, $message, true))->tags();

        $this->assertContains('mail:new_ticket', $newTicketTags);
        $this->assertContains('ticket:55', $newTicketTags);

        $this->assertContains('mail:ticket_status', $statusTags);
        $this->assertContains('ticket_action:updated', $statusTags);

        $this->assertContains('mail:ticket_message', $messageTags);
        $this->assertContains('ticket_message:88', $messageTags);
    }
}
