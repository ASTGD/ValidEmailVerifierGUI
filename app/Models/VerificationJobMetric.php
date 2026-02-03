<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationJobMetric extends Model
{
    protected $primaryKey = 'verification_job_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'verification_job_id',
        'phase',
        'progress_percent',
        'processed_emails',
        'total_emails',
        'cache_hit_count',
        'cache_miss_count',
        'writeback_written_count',
        'peak_cpu_percent',
        'cpu_time_ms',
        'cpu_sampled_at',
        'peak_memory_mb',
        'phase_started_at',
        'phase_updated_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'processed_emails' => 'integer',
        'total_emails' => 'integer',
        'cache_hit_count' => 'integer',
        'cache_miss_count' => 'integer',
        'writeback_written_count' => 'integer',
        'peak_cpu_percent' => 'decimal:2',
        'cpu_time_ms' => 'integer',
        'peak_memory_mb' => 'decimal:2',
        'cpu_sampled_at' => 'datetime',
        'phase_started_at' => 'datetime',
        'phase_updated_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }
}
