<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationOutcome extends Model
{
    protected $fillable = [
        'email_hash',
        'email_normalized',
        'outcome',
        'reason_code',
        'details',
        'observed_at',
        'source',
        'user_id',
    ];

    protected $casts = [
        'details' => 'array',
        'observed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
