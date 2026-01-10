<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionSetting extends Model
{
    protected $fillable = [
        'retention_days',
    ];

    protected $casts = [
        'retention_days' => 'integer',
    ];
}
