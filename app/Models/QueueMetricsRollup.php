<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueMetricsRollup extends Model
{
    protected $fillable = [
        'driver',
        'queue',
        'period_type',
        'period_start',
        'samples',
        'avg_depth',
        'max_depth',
        'avg_oldest_age_seconds',
        'max_oldest_age_seconds',
        'avg_failed_count',
        'max_failed_count',
        'avg_throughput_per_min',
        'max_throughput_per_min',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'samples' => 'integer',
        'avg_depth' => 'float',
        'max_depth' => 'integer',
        'avg_oldest_age_seconds' => 'float',
        'max_oldest_age_seconds' => 'integer',
        'avg_failed_count' => 'float',
        'max_failed_count' => 'integer',
        'avg_throughput_per_min' => 'float',
        'max_throughput_per_min' => 'integer',
    ];
}
