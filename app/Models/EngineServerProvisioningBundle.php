<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngineServerProvisioningBundle extends Model
{
    protected $fillable = [
        'engine_server_id',
        'bundle_uuid',
        'env_key',
        'script_key',
        'token_id',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function engineServer(): BelongsTo
    {
        return $this->belongsTo(EngineServer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
