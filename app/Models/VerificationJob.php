<?php

namespace App\Models;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'origin',
        'original_filename',
        'subject_email',
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
        'cache_miss_key',
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
        'single_result_status',
        'single_result_sub_status',
        'single_result_score',
        'single_result_reason',
        'single_result_verified_at',
    ];

    protected $casts = [
        'status' => VerificationJobStatus::class,
        'verification_mode' => VerificationMode::class,
        'origin' => VerificationJobOrigin::class,
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'prepared_at' => 'datetime',
        'finished_at' => 'datetime',
        'single_result_verified_at' => 'datetime',
        'engine_attempts' => 'integer',
        'total_emails' => 'integer',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'risky_count' => 'integer',
        'unknown_count' => 'integer',
        'cached_count' => 'integer',
        'single_result_score' => 'integer',
    ];

    protected $attributes = [
        'verification_mode' => VerificationMode::Enhanced->value,
        'origin' => VerificationJobOrigin::ListUpload->value,
    ];

    public function scopeExcludeAdminFailures($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('failure_source')
                ->orWhere('failure_source', '!=', self::FAILURE_SOURCE_ADMIN);
        });
    }

    public function scopeExcludeSingleCheck($query)
    {
        return $query->where('origin', '!=', VerificationJobOrigin::SingleCheck->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VerificationJobLog::class, 'verification_job_id');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(VerificationJobMetric::class, 'verification_job_id');
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

    public function seedSendConsents(): HasMany
    {
        return $this->hasMany(SeedSendConsent::class, 'verification_job_id');
    }

    public function seedSendCampaigns(): HasMany
    {
        return $this->hasMany(SeedSendCampaign::class, 'verification_job_id');
    }

    public function seedSendCreditLedgerEntries(): HasMany
    {
        return $this->hasMany(SeedSendCreditLedger::class, 'verification_job_id');
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
