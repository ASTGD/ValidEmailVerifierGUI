<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpDecisionTrace extends Model
{
    protected $fillable = [
        'verification_job_id',
        'verification_job_chunk_id',
        'email_hash',
        'provider',
        'policy_version',
        'matched_rule_id',
        'decision_class',
        'smtp_code',
        'enhanced_code',
        'retry_strategy',
        'reason_tag',
        'confidence_hint',
        'session_strategy_id',
        'attempt_route',
        'trace_payload',
        'observed_at',
    ];

    protected $casts = [
        'verification_job_id' => 'string',
        'verification_job_chunk_id' => 'string',
        'attempt_route' => 'array',
        'trace_payload' => 'array',
        'observed_at' => 'datetime',
    ];

    public function verificationJob(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function verificationJobChunk(): BelongsTo
    {
        return $this->belongsTo(VerificationJobChunk::class, 'verification_job_chunk_id');
    }
}
