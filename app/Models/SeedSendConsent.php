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

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'verification_job_id',
        'user_id',
        'scope',
        'consent_text_version',
        'consent_text_snapshot',
        'consented_at',
        'expires_at',
        'consented_by_user_id',
        'status',
        'approved_by_admin_id',
        'approved_at',
        'revoked_at',
        'revoked_by_admin_id',
        'revocation_reason',
        'rejection_reason',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
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

    public function revokedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_admin_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(SeedSendCampaign::class);
    }
}
