<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineSetting extends Model
{
    protected $fillable = [
        'engine_paused',
        'enhanced_mode_enabled',
    ];

    protected $casts = [
        'engine_paused' => 'boolean',
        'enhanced_mode_enabled' => 'boolean',
    ];
}
