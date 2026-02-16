<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Create a new invoice for a user.
     */
    public function createInvoice(User $user, array $items, array $data = []): Invoice
    {
        return DB::transaction(function () use ($user, $items, $data) {
            $invoice = Invoice::create([
                'user_id' => $user->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'status' => $data['status'] ?? 'Unpaid',
                'date' => $data['date'] ?? Carbon::now(),
                'due_date' => $data['due_date'] ?? Carbon::now()->addDays(7),
                'currency' => $user->currency ?: 'USD',
                'notes' => $data['notes'] ?? null,
                'is_published' => $data['is_published'] ?? true, // Programmatic creations are published by default
            ]);

            $subtotal = 0;
            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'type' => $item['type'] ?? 'Order',
                    'rel_type' => $item['rel_type'] ?? null,
                    'rel_id' => $item['rel_id'] ?? null,
                ]);
                $subtotal += $item['amount'];
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal, // No tax/fees for now
            ]);

            return $invoice;
        });
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, int $amount, string $method, ?string $transactionId = null): Transaction
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $transactionId) {
            $transaction = Transaction::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'transaction_id' => $transactionId,
                'payment_method' => $method,
                'amount' => $amount,
                'date' => Carbon::now(),
            ]);

            // Refresh current state
            $invoice->refresh();

            // Status is handled by the model logic usually, but here we update it directly for legacy/background compatibility
            $totalCents = $invoice->calculateTotal();
            $paidTotal = $invoice->getTotalPaidAttribute();
            $creditsApplied = $invoice->credit_applied ?? 0;
            $balanceCents = max(0, $totalCents - $paidTotal - $creditsApplied);

            if ($balanceCents <= 0) {
                $invoice->update([
                    'status' => 'Paid',
                    'paid_at' => Carbon::now(),
                ]);

                // If this was a credit add invoice, update user balance
                $this->processInvoiceItems($invoice);
            } elseif (($paidTotal + $creditsApplied) > 0) {
                $invoice->update([
                    'status' => 'Partially Paid',
                ]);
            } else {
                $invoice->update([
                    'status' => 'Unpaid',
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Apply a user's credit balance to an invoice.
     */
    public function applyCreditToInvoice(Invoice $invoice, int $amount): bool
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $user = $invoice->user()->lockForUpdate()->first();

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Amount must be greater than zero.');
            }

            if ($user->balance < $amount) {
                throw new \InvalidArgumentException('Insufficient credit balance.');
            }

            $currentBalanceDue = $invoice->calculateBalanceDue();
            if ($amount > $currentBalanceDue) {
                throw new \InvalidArgumentException('Amount exceeds remaining invoice balance.');
            }

            // Deduct from user balance
            $user->decrement('balance', $amount);

            // Use the Invoice model's robust application logic
            return $invoice->applyCredit($amount, "Credit applied from Client Portal balance");
        });
    }

    /**
     * Generate a unique sequential invoice number.
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Y') . '-';
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) Str::after($lastInvoice->invoice_number, $prefix);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Process items in a paid invoice (e.g., adding credit).
     */
    protected function processInvoiceItems(Invoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            if ($item->type === 'Credit') {
                $user = $invoice->user;
                $user->increment('balance', $item->amount);
            }
        }
    }
}
