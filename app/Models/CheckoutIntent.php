<?php

namespace App\Models;

use App\Enums\CheckoutIntentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CheckoutIntent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'status',
        'type',
        'original_filename',
        'temp_disk',
        'temp_key',
        'email_count',
        'amount_cents',
        'currency',
        'pricing_plan_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'payment_method',
        'paid_at',
        'expires_at',
        'credit_applied',
    ];

    protected $casts = [
        'status' => CheckoutIntentStatus::class,
        'email_count' => 'integer',
        'amount_cents' => 'integer',
        'credit_applied' => 'integer',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(VerificationOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
