<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Enums\VerificationMode;
use App\Http\Requests\Verifier\ChunkClaimNextRequest;
use App\Models\EngineServer;
use App\Models\VerificationJobChunk;
use App\Support\AdminAuditLogger;
use App\Support\EngineSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifierChunkClaimNextController
{
    public function __invoke(ChunkClaimNextRequest $request)
    {
        $payload = $request->validated();
        $serverData = $payload['engine_server'];
        $workerCapability = $this->resolveWorkerCapability((string) ($payload['worker_capability'] ?? 'all'));
        $serverMeta = is_array($serverData['meta'] ?? null) ? $serverData['meta'] : [];
        $workerPool = $this->normalizeString($serverMeta['pool'] ?? null);
        $providerAffinity = $this->normalizeProvider($serverMeta['provider_affinity'] ?? null);
        $trustTier = $this->normalizeString($serverMeta['trust_tier'] ?? null);

        $update = [
            'name' => $serverData['name'],
            'last_heartbeat_at' => now(),
        ];

        if (array_key_exists('environment', $serverData)) {
            $update['environment'] = $serverData['environment'];
        }

        if (array_key_exists('region', $serverData)) {
            $update['region'] = $serverData['region'];
        }

        $server = EngineServer::query()->updateOrCreate(
            ['ip_address' => $serverData['ip_address']],
            $update
        );
        $server->refresh();

        AdminAuditLogger::log('engine_claim_next', $server, [
            'ip_address' => $server->ip_address,
            'name' => $server->name,
            'environment' => $server->environment,
            'region' => $server->region,
            'worker_id' => $payload['worker_id'],
            'worker_capability' => $workerCapability,
            'worker_pool' => $workerPool,
            'worker_provider_affinity' => $providerAffinity,
            'worker_trust_tier' => $trustTier,
        ]);

        if (EngineSettings::enginePaused()) {
            return response()->noContent();
        }

        if ($server->is_active === false || $server->drain_mode === true) {
            return response()->noContent();
        }

        $leaseSeconds = (int) ($payload['lease_seconds'] ?? config('engine.lease_seconds', 600));
        $leaseSeconds = max(1, $leaseSeconds);
        $now = now();

        $chunk = DB::transaction(function () use (
            $server,
            $payload,
            $leaseSeconds,
            $now,
            $workerCapability,
            $workerPool,
            $providerAffinity
        ) {
            $chunk = $this->selectClaimableChunk(
                $now,
                $workerCapability,
                (string) ($payload['worker_id'] ?? ''),
                $workerPool,
                $providerAffinity
            );

            if (! $chunk) {
                return null;
            }

            $chunk->update([
                'status' => 'processing',
                'engine_server_id' => $server->id,
                'assigned_worker_id' => $payload['worker_id'],
                'claimed_at' => $now,
                'claim_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                'claim_token' => (string) Str::uuid(),
            ]);

            return $chunk->fresh();
        });

        if (! $chunk) {
            return response()->noContent();
        }

        $chunk->job?->addLog('chunk_claimed', 'Chunk claimed by engine worker.', [
            'chunk_id' => (string) $chunk->id,
            'chunk_no' => $chunk->chunk_no,
            'engine_server_id' => $chunk->engine_server_id,
            'worker_id' => $chunk->assigned_worker_id,
            'claim_expires_at' => $chunk->claim_expires_at?->toIso8601String(),
        ], $request->user()?->id);

        $processingStage = $this->normalizeProcessingStage((string) ($chunk->processing_stage ?? ''));

        return response()->json([
            'data' => [
                'chunk_id' => (string) $chunk->id,
                'job_id' => (string) $chunk->verification_job_id,
                'chunk_no' => $chunk->chunk_no,
                'verification_mode' => $chunk->job?->verification_mode?->value ?? VerificationMode::Enhanced->value,
                'processing_stage' => $processingStage,
                'worker_capability_required' => $this->capabilityForStage($processingStage),
                'routing_provider' => $chunk->routing_provider,
                'preferred_pool' => $chunk->preferred_pool,
                'max_probe_attempts' => $chunk->max_probe_attempts,
                'retry_attempt' => $chunk->retry_attempt,
                'last_worker_ids' => is_array($chunk->last_worker_ids) ? array_values($chunk->last_worker_ids) : [],
                'lease_expires_at' => $chunk->claim_expires_at?->toIso8601String(),
                'input' => [
                    'disk' => $chunk->input_disk,
                    'key' => $chunk->input_key,
                ],
            ],
        ]);
    }

    private function resolveWorkerCapability(string $value): string
    {
        $value = strtolower(trim($value));

        if (in_array($value, ['screening', 'smtp_probe', 'all'], true)) {
            return $value;
        }

        return 'all';
    }

    private function normalizeProcessingStage(string $value): string
    {
        $value = strtolower(trim($value));

        return $value === 'smtp_probe' ? 'smtp_probe' : 'screening';
    }

    private function capabilityForStage(string $stage): string
    {
        return $stage === 'smtp_probe' ? 'smtp_probe' : 'screening';
    }

    private function selectClaimableChunk(
        $now,
        string $workerCapability,
        string $workerId,
        ?string $workerPool,
        ?string $providerAffinity
    ): ?VerificationJobChunk {
        $query = VerificationJobChunk::query()
            ->where('status', 'pending')
            ->when($workerCapability !== 'all', function ($query) use ($workerCapability) {
                if ($workerCapability === 'screening') {
                    $query->where(function ($stageQuery) {
                        $stageQuery->where('processing_stage', 'screening')
                            ->orWhereNull('processing_stage');
                    });

                    return;
                }

                $query->where('processing_stage', $workerCapability);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('available_at')
                    ->orWhere('available_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('claim_expires_at')
                    ->orWhere('claim_expires_at', '<', $now);
            })
            ->where(function ($query) {
                $query->where(function ($smtpQuery) {
                    $smtpQuery->where('processing_stage', 'smtp_probe')
                        ->whereRaw('COALESCE(retry_attempt, 0) < COALESCE(max_probe_attempts, 3)');
                })->orWhere(function ($otherStagesQuery) {
                    $otherStagesQuery->where('processing_stage', '!=', 'smtp_probe')
                        ->orWhereNull('processing_stage');
                });
            });

        $candidates = $query
            ->orderBy('created_at')
            ->lockForUpdate()
            ->limit(50)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if (! $this->shouldApplyProbeRouting($workerCapability)) {
            return $candidates->first();
        }

        $ranked = $candidates->sort(function (VerificationJobChunk $a, VerificationJobChunk $b) use ($workerId, $workerPool, $providerAffinity) {
            $scoreA = $this->routingScore($a, $workerId, $workerPool, $providerAffinity);
            $scoreB = $this->routingScore($b, $workerId, $workerPool, $providerAffinity);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            if ($a->created_at && $b->created_at && $a->created_at->ne($b->created_at)) {
                return $a->created_at->lt($b->created_at) ? -1 : 1;
            }

            return (int) $a->chunk_no <=> (int) $b->chunk_no;
        });

        $selected = $ranked->first();
        if (! $selected) {
            return null;
        }

        if (! (bool) config('engine.probe_rotation_retry_enabled', true)) {
            return $selected;
        }

        if (! (bool) config('engine.retry_anti_affinity_hard_enabled', true)) {
            return $selected;
        }

        $selectedRetryAttempt = max(0, (int) $selected->retry_attempt);
        if ($selectedRetryAttempt <= 0) {
            return $selected;
        }

        if (! $this->retryTouchesCurrentRoute($selected, $workerId, $workerPool)) {
            return $selected;
        }

        $alternative = $ranked->first(function (VerificationJobChunk $candidate) use ($workerId, $workerPool): bool {
            if (max(0, (int) $candidate->retry_attempt) <= 0) {
                return false;
            }

            return ! $this->retryTouchesCurrentRoute($candidate, $workerId, $workerPool);
        });

        return $alternative ?: $selected;
    }

    private function shouldApplyProbeRouting(string $workerCapability): bool
    {
        if (! (bool) config('engine.probe_routing_enabled', true)) {
            return false;
        }

        return $workerCapability === 'smtp_probe';
    }

    private function routingScore(
        VerificationJobChunk $chunk,
        string $workerId,
        ?string $workerPool,
        ?string $providerAffinity
    ): int {
        $score = 0;
        $weights = $this->probeRoutingWeights();

        $chunkProvider = $this->normalizeProvider($chunk->routing_provider);
        $chunkPreferredPool = $this->normalizeString($chunk->preferred_pool);
        if ($chunkPreferredPool === null && $chunkProvider !== null) {
            $chunkPreferredPool = $this->providerPreferredPoolFromConfig($chunkProvider);
        }

        if ($providerAffinity !== null && $chunkProvider !== null && $providerAffinity === $chunkProvider) {
            $score += $weights['affinity'];
        }

        if ($workerPool !== null && $chunkPreferredPool !== null && $workerPool === $chunkPreferredPool) {
            $score += $weights['preferred_pool'];
        }

        if ((bool) config('engine.probe_rotation_retry_enabled', true)) {
            $lastWorkerIds = is_array($chunk->last_worker_ids) ? $chunk->last_worker_ids : [];
            $normalizedLastWorkerIds = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $lastWorkerIds
            )));

            if (in_array($workerId, $normalizedLastWorkerIds, true)) {
                $score -= $weights['anti_affinity'];
            }
        }

        $retryAttempt = max(0, (int) $chunk->retry_attempt);
        if ($retryAttempt > 0) {
            $score += $weights['retry_bonus'];
        }

        return $score;
    }

    /**
     * @return array{affinity:int,anti_affinity:int,preferred_pool:int,retry_bonus:int}
     */
    private function probeRoutingWeights(): array
    {
        $weights = config('engine.probe_routing_weights', []);

        return [
            'affinity' => max(0, (int) data_get($weights, 'affinity', 40)),
            'anti_affinity' => max(0, (int) data_get($weights, 'anti_affinity', 60)),
            'preferred_pool' => max(0, (int) data_get($weights, 'preferred_pool', 30)),
            'retry_bonus' => max(0, (int) data_get($weights, 'retry_bonus', 10)),
        ];
    }

    private function providerPreferredPoolFromConfig(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            return null;
        }

        $raw = (string) config('engine.probe_provider_preferred_pools', '');
        if ($raw === '') {
            return null;
        }

        foreach (explode(',', $raw) as $entry) {
            [$entryProvider, $pool] = array_pad(explode(':', trim($entry), 2), 2, null);
            if ($entryProvider === null || $pool === null) {
                continue;
            }

            if (strtolower(trim($entryProvider)) !== $provider) {
                continue;
            }

            $normalizedPool = trim($pool);

            return $normalizedPool === '' ? null : $normalizedPool;
        }

        return null;
    }

    private function normalizeProvider(mixed $value): ?string
    {
        $provider = strtolower(trim((string) $value));
        if ($provider === '') {
            return null;
        }

        return in_array($provider, ['gmail', 'microsoft', 'yahoo', 'generic'], true)
            ? $provider
            : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function retryTouchesCurrentRoute(VerificationJobChunk $chunk, string $workerId, ?string $workerPool): bool
    {
        $lastWorkerIds = is_array($chunk->last_worker_ids) ? $chunk->last_worker_ids : [];
        $normalizedLastWorkerIds = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $lastWorkerIds
        )));
        $sameWorker = in_array($workerId, $normalizedLastWorkerIds, true);

        $chunkPreferredPool = $this->normalizeString($chunk->preferred_pool);
        $samePool = $workerPool !== null
            && $chunkPreferredPool !== null
            && $workerPool === $chunkPreferredPool;

        return $sameWorker || $samePool;
    }
}
