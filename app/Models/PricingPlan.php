<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'billing_interval',
        'price_per_email',
        'price_per_1000',
        'min_emails',
        'max_emails',
        'credits_per_month',
        'max_file_size_mb',
        'concurrency_limit',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'price_per_email' => 'decimal:4',
        'price_per_1000' => 'decimal:2',
        'min_emails' => 'integer',
        'max_emails' => 'integer',
        'credits_per_month' => 'integer',
        'max_file_size_mb' => 'integer',
        'concurrency_limit' => 'integer',
        'is_active' => 'boolean',
    ];
}
