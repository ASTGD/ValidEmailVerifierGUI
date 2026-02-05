<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'amount',
        'type',
        'rel_type',
        'rel_id',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo('rel');
    }

    public function getFormattedAmountAttribute(): string
    {
        // Assuming parent invoice currency or default to USD if not accessible easily, 
        // but ideally we should access parent invoice currency.
        // For simplicity, let's just format the number. 
        // Or fetch invoice relation if loaded.
        $currency = $this->invoice->currency ?? 'USD';
        return number_format($this->amount / 100, 2) . ' ' . $currency;
    }
}
