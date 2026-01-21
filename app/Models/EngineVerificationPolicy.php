<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineVerificationPolicy extends Model
{
    protected $fillable = [
        'mode',
        'enabled',
        'dns_timeout_ms',
        'smtp_connect_timeout_ms',
        'smtp_read_timeout_ms',
        'max_mx_attempts',
        'max_concurrency_default',
        'per_domain_concurrency',
        'catch_all_detection_enabled',
        'global_connects_per_minute',
        'tempfail_backoff_seconds',
        'circuit_breaker_tempfail_rate',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'dns_timeout_ms' => 'integer',
        'smtp_connect_timeout_ms' => 'integer',
        'smtp_read_timeout_ms' => 'integer',
        'max_mx_attempts' => 'integer',
        'max_concurrency_default' => 'integer',
        'per_domain_concurrency' => 'integer',
        'catch_all_detection_enabled' => 'boolean',
        'global_connects_per_minute' => 'integer',
        'tempfail_backoff_seconds' => 'integer',
        'circuit_breaker_tempfail_rate' => 'float',
    ];
}
