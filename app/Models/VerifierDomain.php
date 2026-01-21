<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerifierDomain extends Model
{
    protected $fillable = [
        'domain',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function engineServers(): HasMany
    {
        return $this->hasMany(EngineServer::class);
    }
}
