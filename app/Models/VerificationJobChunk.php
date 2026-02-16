<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationJobChunk extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'verification_job_id',
        'chunk_no',
        'status',
        'processing_stage',
        'parent_chunk_id',
        'source_stage',
        'routing_provider',
        'routing_domain',
        'preferred_pool',
        'rotation_group_id',
        'last_worker_ids',
        'max_probe_attempts',
        'input_disk',
        'input_key',
        'output_disk',
        'valid_key',
        'invalid_key',
        'risky_key',
        'email_count',
        'valid_count',
        'invalid_count',
        'risky_count',
        'attempts',
        'engine_server_id',
        'assigned_worker_id',
        'claimed_at',
        'claim_expires_at',
        'available_at',
        'claim_token',
        'retry_attempt',
        'retry_parent_id',
    ];

    protected $casts = [
        'email_count' => 'integer',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'risky_count' => 'integer',
        'attempts' => 'integer',
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'available_at' => 'datetime',
        'retry_attempt' => 'integer',
        'last_worker_ids' => 'array',
        'max_probe_attempts' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }

    public function parentChunk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_chunk_id');
    }

    public function childChunks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_chunk_id');
    }

    public function smtpDecisionTraces(): HasMany
    {
        return $this->hasMany(SmtpDecisionTrace::class, 'verification_job_chunk_id');
    }
}
