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
        'process_control_mode',
        'agent_enabled',
        'agent_base_url',
        'agent_timeout_seconds',
        'agent_verify_tls',
        'agent_service_name',
        'last_agent_status',
        'last_agent_seen_at',
        'last_agent_error',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'is_active' => 'boolean',
        'drain_mode' => 'boolean',
        'max_concurrency' => 'integer',
        'agent_enabled' => 'boolean',
        'agent_timeout_seconds' => 'integer',
        'agent_verify_tls' => 'boolean',
        'last_agent_status' => 'array',
        'last_agent_seen_at' => 'datetime',
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

    public function commands(): HasMany
    {
        return $this->hasMany(EngineServerCommand::class);
    }

    public function latestProvisioningBundle(): HasOne
    {
        return $this->hasOne(EngineServerProvisioningBundle::class)->latestOfMany();
    }

    public function verifierDomain(): BelongsTo
    {
        return $this->belongsTo(VerifierDomain::class);
    }

    public function supportsAgentProcessControl(): bool
    {
        return $this->process_control_mode === 'agent_systemd' && $this->agent_enabled;
    }
}
