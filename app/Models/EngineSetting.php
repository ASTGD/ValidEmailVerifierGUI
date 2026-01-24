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
        'catch_all_policy',
        'catch_all_promote_threshold',
    ];

    protected $casts = [
        'engine_paused' => 'boolean',
        'enhanced_mode_enabled' => 'boolean',
        'catch_all_promote_threshold' => 'integer',
    ];
}
