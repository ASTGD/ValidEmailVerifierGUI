<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueIncident extends Model
{
    protected $fillable = [
        'issue_key',
        'severity',
        'status',
        'lane',
        'title',
        'detail',
        'first_detected_at',
        'last_detected_at',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'mitigated_at',
        'resolved_at',
        'meta',
    ];

    protected $casts = [
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'mitigated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'meta' => 'array',
    ];
}
