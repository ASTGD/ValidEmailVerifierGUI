<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicyVersion extends Model
{
    protected $fillable = [
        'version',
        'status',
        'is_active',
        'policy_payload',
        'created_by',
        'promoted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'policy_payload' => 'array',
        'promoted_at' => 'datetime',
    ];
}
