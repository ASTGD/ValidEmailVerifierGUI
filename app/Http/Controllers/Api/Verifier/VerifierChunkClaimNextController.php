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

        return $ranked->first();
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

        $chunkProvider = $this->normalizeProvider($chunk->routing_provider);
        $chunkPreferredPool = $this->normalizeString($chunk->preferred_pool);

        if ($providerAffinity !== null && $chunkProvider !== null && $providerAffinity === $chunkProvider) {
            $score += 40;
        }

        if ($workerPool !== null && $chunkPreferredPool !== null && $workerPool === $chunkPreferredPool) {
            $score += 30;
        }

        if ((bool) config('engine.probe_rotation_retry_enabled', true)) {
            $lastWorkerIds = is_array($chunk->last_worker_ids) ? $chunk->last_worker_ids : [];
            $normalizedLastWorkerIds = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $lastWorkerIds
            )));

            if (in_array($workerId, $normalizedLastWorkerIds, true)) {
                $score -= 60;
            }
        }

        $retryAttempt = max(0, (int) $chunk->retry_attempt);
        if ($retryAttempt > 0) {
            $score += 10;
        }

        return $score;
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
}
