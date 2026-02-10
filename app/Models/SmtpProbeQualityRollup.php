<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpProbeQualityRollup extends Model
{
    protected $fillable = [
        'rollup_date',
        'provider',
        'sample_count',
        'unknown_count',
        'tempfail_count',
        'policy_blocked_count',
        'retry_success_count',
        'unknown_rate',
        'tempfail_recovery_rate',
        'policy_blocked_rate',
        'retry_waste_rate',
    ];

    protected $casts = [
        'rollup_date' => 'date',
        'sample_count' => 'integer',
        'unknown_count' => 'integer',
        'tempfail_count' => 'integer',
        'policy_blocked_count' => 'integer',
        'retry_success_count' => 'integer',
        'unknown_rate' => 'float',
        'tempfail_recovery_rate' => 'float',
        'policy_blocked_rate' => 'float',
        'retry_waste_rate' => 'float',
    ];
}
