<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpTruthLabel extends Model
{
    protected $fillable = [
        'email_hash',
        'provider',
        'truth_label',
        'confidence_hint',
        'source',
        'source_campaign_id',
        'source_recipient_id',
        'decision_class',
        'reason_tag',
        'evidence_payload',
        'observed_at',
    ];

    protected $casts = [
        'evidence_payload' => 'array',
        'observed_at' => 'datetime',
    ];
}
