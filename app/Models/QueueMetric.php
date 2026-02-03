<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueMetric extends Model
{
    protected $fillable = [
        'driver',
        'queue',
        'depth',
        'failed_count',
        'oldest_age_seconds',
        'throughput_per_min',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'depth' => 'integer',
        'failed_count' => 'integer',
        'oldest_age_seconds' => 'integer',
        'throughput_per_min' => 'integer',
    ];
}
