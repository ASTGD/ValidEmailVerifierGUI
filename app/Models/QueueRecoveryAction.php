<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueRecoveryAction extends Model
{
    protected $fillable = [
        'action_type',
        'strategy',
        'status',
        'lane',
        'job_class',
        'requested_by_user_id',
        'target_count',
        'processed_count',
        'failed_count',
        'dry_run',
        'reason',
        'meta',
        'executed_at',
    ];

    protected $casts = [
        'target_count' => 'integer',
        'processed_count' => 'integer',
        'failed_count' => 'integer',
        'dry_run' => 'boolean',
        'meta' => 'array',
        'executed_at' => 'datetime',
    ];
}
