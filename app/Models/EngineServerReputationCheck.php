<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerReputationCheck extends Model
{
    protected $fillable = [
        'engine_server_id',
        'ip_address',
        'rbl',
        'status',
        'response',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }
}
