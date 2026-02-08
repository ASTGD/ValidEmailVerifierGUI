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

        $chunk = DB::transaction(function () use ($server, $payload, $leaseSeconds, $now, $workerCapability) {
            $chunk = VerificationJobChunk::query()
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
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

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
}
