<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngineServer extends Model
{
    protected $fillable = [
        'name',
        'ip_address',
        'environment',
        'region',
        'last_heartbeat_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isOnline(): bool
    {
        if (! $this->is_active || ! $this->last_heartbeat_at) {
            return false;
        }

        $threshold = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));

        return $this->last_heartbeat_at->greaterThan(now()->subMinutes($threshold));
    }
}
