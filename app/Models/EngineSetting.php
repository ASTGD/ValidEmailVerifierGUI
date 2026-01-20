<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineSetting extends Model
{
    protected $fillable = [
        'engine_paused',
        'enhanced_mode_enabled',
        'role_accounts_behavior',
        'role_accounts_list',
    ];

    protected $casts = [
        'engine_paused' => 'boolean',
        'enhanced_mode_enabled' => 'boolean',
    ];
}
