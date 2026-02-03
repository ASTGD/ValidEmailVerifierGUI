<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationJobChunk extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'verification_job_id',
        'chunk_no',
        'status',
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
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }
}
