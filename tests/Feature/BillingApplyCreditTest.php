<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApplyCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_credit_reduces_balance_and_updates_invoice_status()
    {
        $user = User::factory()->create(['balance' => 10000]); // $100.00

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-TEST-00001',
            'status' => 'Unpaid',
            'date' => now(),
            'due_date' => now()->addDays(7),
            'subtotal' => 5000,
            'total' => 5000,
            'currency' => 'USD',
        ]);

        $billing = app(BillingService::class);

        // Apply partial credit
        $txn = $billing->applyCreditToInvoice($invoice, 2500);
        $this->assertDatabaseHas('transactions', ['id' => $txn->id, 'amount' => 2500]);

        $user->refresh();
        $this->assertEquals(7500, $user->balance);

        $invoice->refresh();
        $this->assertEquals('Partially Paid', $invoice->status);

        // Apply remaining credit
        $billing->applyCreditToInvoice($invoice, 2500);

        $user->refresh();
        $this->assertEquals(5000, $user->balance);

        $invoice->refresh();
        $this->assertEquals('Paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }
}
