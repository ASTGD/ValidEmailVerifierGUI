<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeedSendEvent extends Model
{
    protected $fillable = [
        'campaign_id',
        'recipient_id',
        'provider',
        'event_type',
        'event_time',
        'smtp_code',
        'enhanced_code',
        'provider_message_id',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'event_time' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SeedSendCampaign::class, 'campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SeedSendRecipient::class, 'recipient_id');
    }
}
