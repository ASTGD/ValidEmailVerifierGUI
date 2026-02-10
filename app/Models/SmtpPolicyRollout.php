<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicyRollout extends Model
{
    protected $fillable = [
        'policy_version',
        'provider',
        'canary_percent',
        'status',
        'triggered_by',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'canary_percent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
