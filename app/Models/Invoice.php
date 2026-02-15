<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_number',
        'status',
        'date',
        'due_date',
        'paid_at',
        'subtotal',
        'tax',
        'discount',
        'total',
        'credit_applied',
        'balance_due',
        'currency',
        'notes',
        'payment_method',
        'is_published',
    ];

    protected $casts = [
        'date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'integer',
        'tax' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
        'credit_applied' => 'integer',
        'balance_due' => 'integer',
        'is_published' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class, 'invoice_id');
    }

    /**
     * Calculate the total amount from all invoice items
     */
    public function calculateTotal(): int
    {
        $itemsTotal = $this->items()->sum('amount');
        $tax = $this->tax ?? 0;
        $discount = $this->discount ?? 0;

        return max(0, $itemsTotal + $tax - $discount);
    }

    /**
     * Calculate the balance due after payments and credits
     */
    public function calculateBalanceDue(): int
    {
        $total = $this->total ?? $this->calculateTotal();
        $paid = $this->transactions()->sum('amount');
        $creditApplied = $this->credit_applied ?? 0;

        return max(0, $total - $paid - $creditApplied);
    }

    /**
     * Get the total amount paid via transactions
     */
    public function getTotalPaidAttribute(): int
    {
        return $this->transactions()->sum('amount');
    }

    /**
     * Apply credit to this invoice
     */
    public function applyCredit(int $amount, string $description = null): bool
    {
        $maxCredit = $this->balance_due ?? $this->calculateBalanceDue();
        $amount = min($amount, $maxCredit);

        if ($amount <= 0) {
            return false;
        }

        // Update credit_applied
        $this->credit_applied = ($this->credit_applied ?? 0) + $amount;
        $this->balance_due = $this->calculateBalanceDue();

        // Update status if fully paid
        if ($this->balance_due <= 0) {
            $this->status = 'Paid';
            $this->paid_at = now();
        } elseif ($this->credit_applied > 0 || $this->total_paid > 0) {
            $this->status = 'Partially Paid';
        }

        $this->save();

        // Record credit transaction
        Credit::create([
            'user_id' => $this->user_id,
            'invoice_id' => $this->id,
            'amount' => -$amount, // Negative because we're using credit
            'description' => $description ?? "Credit applied to Invoice #{$this->invoice_number}",
            'type' => 'used',
        ]);

        return true;
    }

    /**
     * Process a payment for this invoice
     */
    public function processPayment(int $amount, string $paymentMethod, string $transactionId = null): bool
    {
        $maxPayment = $this->balance_due ?? $this->calculateBalanceDue();
        $amount = min($amount, $maxPayment);

        if ($amount <= 0) {
            return false;
        }

        // Create transaction
        Transaction::create([
            'invoice_id' => $this->id,
            'user_id' => $this->user_id,
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'date' => now(),
        ]);

        // Update invoice
        $this->balance_due = $this->calculateBalanceDue();
        $this->payment_method = $paymentMethod;

        if ($this->balance_due <= 0) {
            $this->status = 'Paid';
            $this->paid_at = now();
        } else {
            $this->status = 'Partially Paid';
        }

        $this->save();

        return true;
    }

    /**
     * Process a refund for this invoice
     */
    public function processRefund(int $amount, string $reason = null): bool
    {
        if ($amount <= 0) {
            return false;
        }

        // Create negative transaction for refund
        Transaction::create([
            'invoice_id' => $this->id,
            'user_id' => $this->user_id,
            'transaction_id' => null,
            'payment_method' => 'Refund',
            'amount' => -$amount,
            'date' => now(),
        ]);

        // Update invoice status
        $this->balance_due = $this->calculateBalanceDue();
        $totalRefunded = $this->transactions()->where('amount', '<', 0)->sum('amount');

        if (abs($totalRefunded) >= $this->total) {
            $this->status = 'Refunded';
        } else {
            $this->status = 'Partially Refunded';
        }

        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n\n" : '') . "Refund: " . $reason;
        }

        $this->save();

        return true;
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->calculateTotal() / 100, 2) . ' ' . strtoupper($this->currency);
    }

    public function getFormattedBalanceDueAttribute(): string
    {
        $balance = $this->balance_due ?? $this->calculateBalanceDue();
        return number_format($balance / 100, 2) . ' ' . strtoupper($this->currency);
    }
}
