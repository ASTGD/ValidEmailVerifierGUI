<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeedSendConsent extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'verification_job_id',
        'user_id',
        'scope',
        'consent_text_version',
        'consented_at',
        'consented_by_user_id',
        'status',
        'approved_by_admin_id',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consented_by_user_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(SeedSendCampaign::class);
    }
}
