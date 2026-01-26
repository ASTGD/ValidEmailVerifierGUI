<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerDelistRequest extends Model
{
    protected $fillable = [
        'engine_server_id',
        'rbl',
        'status',
        'notes',
        'requested_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
