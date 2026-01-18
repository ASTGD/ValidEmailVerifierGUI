<?php

namespace App\Models;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class VerificationJob extends Model
{
    use HasUuids;

    public const FAILURE_SOURCE_ENGINE = 'engine';
    public const FAILURE_SOURCE_ADMIN = 'admin';
    public const FAILURE_SOURCE_SYSTEM = 'system';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'status',
        'verification_mode',
        'original_filename',
        'input_disk',
        'input_key',
        'output_disk',
        'output_key',
        'valid_key',
        'invalid_key',
        'risky_key',
        'cached_valid_key',
        'cached_invalid_key',
        'cached_risky_key',
        'engine_server_id',
        'claimed_at',
        'claim_expires_at',
        'claim_token',
        'engine_attempts',
        'error_message',
        'failure_source',
        'failure_code',
        'started_at',
        'prepared_at',
        'finished_at',
        'total_emails',
        'valid_count',
        'invalid_count',
        'risky_count',
        'unknown_count',
        'cached_count',
    ];

    protected $casts = [
        'status' => VerificationJobStatus::class,
        'verification_mode' => VerificationMode::class,
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'prepared_at' => 'datetime',
        'finished_at' => 'datetime',
        'engine_attempts' => 'integer',
        'total_emails' => 'integer',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'risky_count' => 'integer',
        'unknown_count' => 'integer',
        'cached_count' => 'integer',
    ];

    protected $attributes = [
        'verification_mode' => VerificationMode::Standard->value,
    ];

    public function scopeExcludeAdminFailures($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('failure_source')
                ->orWhere('failure_source', '!=', self::FAILURE_SOURCE_ADMIN);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VerificationJobLog::class, 'verification_job_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(VerificationJobChunk::class, 'verification_job_id');
    }

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(VerificationOrder::class, 'verification_job_id');
    }

    public function addLog(string $event, ?string $message = null, ?array $context = null, ?int $userId = null): VerificationJobLog
    {
        return $this->logs()->create([
            'user_id' => $userId,
            'event' => $event,
            'message' => $message,
            'context' => $context,
        ]);
    }

    protected static function booted(): void
    {
        static::updated(function (VerificationJob $job) {
            if (! $job->wasChanged('status')) {
                return;
            }

            if (! $job->relationLoaded('order')) {
                $job->load('order');
            }

            if ($job->order) {
                $job->order->syncStatusFromJob($job);
            }
        });
    }
}
