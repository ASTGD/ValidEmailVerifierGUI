<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicyActionAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'policy_version',
        'provider',
        'source',
        'actor',
        'result',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
