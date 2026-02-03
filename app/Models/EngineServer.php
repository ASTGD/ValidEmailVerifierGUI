<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EngineServer extends Model
{
    protected $fillable = [
        'name',
        'ip_address',
        'environment',
        'region',
        'last_heartbeat_at',
        'is_active',
        'drain_mode',
        'max_concurrency',
        'helo_name',
        'mail_from_address',
        'identity_domain',
        'verifier_domain_id',
        'notes',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'is_active' => 'boolean',
        'drain_mode' => 'boolean',
        'max_concurrency' => 'integer',
    ];

    public function isOnline(): bool
    {
        if (! $this->is_active || ! $this->last_heartbeat_at) {
            return false;
        }

        $threshold = max(1, (int) config('verifier.engine_heartbeat_minutes', 5));

        return $this->last_heartbeat_at->greaterThan(now()->subMinutes($threshold));
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(VerificationJobChunk::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(VerificationJob::class);
    }

    public function latestActiveJob(): HasOne
    {
        return $this->hasOne(VerificationJob::class)
            ->where('status', 'processing')
            ->latestOfMany('started_at');
    }

    public function reputationSamples(): HasMany
    {
        return $this->hasMany(EngineServerReputationSample::class);
    }

    public function reputationChecks(): HasMany
    {
        return $this->hasMany(EngineServerReputationCheck::class);
    }

    public function blacklistEvents(): HasMany
    {
        return $this->hasMany(EngineServerBlacklistEvent::class);
    }

    public function delistRequests(): HasMany
    {
        return $this->hasMany(EngineServerDelistRequest::class);
    }

    public function provisioningBundles(): HasMany
    {
        return $this->hasMany(EngineServerProvisioningBundle::class);
    }

    public function verifierDomain(): BelongsTo
    {
        return $this->belongsTo(VerifierDomain::class);
    }
}
