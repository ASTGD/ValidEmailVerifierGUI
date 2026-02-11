<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeedSendRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHING = 'dispatching';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'email',
        'email_hash',
        'status',
        'attempt_count',
        'last_attempt_at',
        'last_event_at',
        'provider_message_id',
        'provider_payload',
        'evidence_payload',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'provider_payload' => 'array',
        'evidence_payload' => 'array',
        'last_attempt_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SeedSendCampaign::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SeedSendEvent::class, 'recipient_id');
    }
}
