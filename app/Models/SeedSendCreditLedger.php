<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeedSendCreditLedger extends Model
{
    protected $table = 'seed_send_credit_ledger';

    protected $fillable = [
        'campaign_id',
        'verification_job_id',
        'user_id',
        'entry_type',
        'credits',
        'reference_key',
        'metadata',
    ];

    protected $casts = [
        'credits' => 'integer',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SeedSendCampaign::class, 'campaign_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
