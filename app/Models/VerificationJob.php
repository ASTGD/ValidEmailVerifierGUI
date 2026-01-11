<?php

namespace App\Models;

use App\Enums\VerificationJobStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class VerificationJob extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'status',
        'original_filename',
        'input_disk',
        'input_key',
        'output_disk',
        'output_key',
        'error_message',
        'started_at',
        'finished_at',
        'total_emails',
        'valid_count',
        'invalid_count',
        'risky_count',
        'unknown_count',
    ];

    protected $casts = [
        'status' => VerificationJobStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_emails' => 'integer',
        'valid_count' => 'integer',
        'invalid_count' => 'integer',
        'risky_count' => 'integer',
        'unknown_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VerificationJobLog::class, 'verification_job_id');
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
