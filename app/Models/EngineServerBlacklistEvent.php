<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerBlacklistEvent extends Model
{
    protected $fillable = [
        'engine_server_id',
        'rbl',
        'status',
        'severity',
        'first_seen',
        'last_seen',
        'last_response',
        'listed_count',
    ];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'listed_count' => 'integer',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }
}
