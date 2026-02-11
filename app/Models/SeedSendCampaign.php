<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeedSendCampaign extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'verification_job_id',
        'user_id',
        'seed_send_consent_id',
        'approved_by_admin_id',
        'status',
        'target_scope',
        'provider',
        'provider_campaign_ref',
        'report_disk',
        'report_key',
        'target_count',
        'sent_count',
        'delivered_count',
        'bounced_count',
        'deferred_count',
        'credits_reserved',
        'credits_used',
        'started_at',
        'finished_at',
        'paused_at',
        'pause_reason',
        'failure_reason',
    ];

    protected $casts = [
        'target_count' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'bounced_count' => 'integer',
        'deferred_count' => 'integer',
        'credits_reserved' => 'integer',
        'credits_used' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(SeedSendConsent::class, 'seed_send_consent_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SeedSendRecipient::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SeedSendEvent::class, 'campaign_id');
    }

    public function creditLedgerEntries(): HasMany
    {
        return $this->hasMany(SeedSendCreditLedger::class, 'campaign_id');
    }
}
