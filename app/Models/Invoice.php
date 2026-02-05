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
        'total',
        'currency',
        'notes',
    ];

    protected $casts = [
        'date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'integer',
        'total' => 'integer',
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

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total / 100, 2) . ' ' . strtoupper($this->currency);
    }
}
