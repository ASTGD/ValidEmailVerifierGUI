<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpPolicyVersion extends Model
{
    protected $fillable = [
        'version',
        'status',
        'validation_status',
        'validation_errors',
        'validated_at',
        'validated_by',
        'is_active',
        'policy_payload',
        'created_by',
        'promoted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'policy_payload' => 'array',
        'validation_errors' => 'array',
        'validated_at' => 'datetime',
        'promoted_at' => 'datetime',
    ];
}
