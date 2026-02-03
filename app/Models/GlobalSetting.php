<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    protected $fillable = [
        'devtools_enabled',
        'devtools_environments',
    ];

    protected $casts = [
        'devtools_enabled' => 'boolean',
    ];
}
