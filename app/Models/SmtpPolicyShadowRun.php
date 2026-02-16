<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicyShadowRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'candidate_version',
        'active_version',
        'provider',
        'status',
        'sample_size',
        'unknown_rate_delta',
        'tempfail_recovery_delta',
        'policy_block_rate_delta',
        'drift_summary',
        'evaluated_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'sample_size' => 'integer',
        'unknown_rate_delta' => 'float',
        'tempfail_recovery_delta' => 'float',
        'policy_block_rate_delta' => 'float',
        'drift_summary' => 'array',
        'evaluated_at' => 'datetime',
    ];
}
