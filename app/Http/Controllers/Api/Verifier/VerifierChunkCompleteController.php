<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Http\Requests\Verifier\ChunkCompleteRequest;
use App\Jobs\FinalizeVerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\EngineServerReputationRecorder;
use App\Services\JobMetricsRecorder;
use App\Services\ScreeningToProbeChunkPlanner;
use App\Services\TempfailRetryPlanner;
use App\Support\SmtpProbeStage;
use Illuminate\Http\JsonResponse;
use Throwable;

class VerifierChunkCompleteController
{
    public function __invoke(
        ChunkCompleteRequest $request,
        VerificationJobChunk $chunk,
        TempfailRetryPlanner $retryPlanner,
        ScreeningToProbeChunkPlanner $probePlanner,
        JobMetricsRecorder $metricsRecorder,
        EngineServerReputationRecorder $reputationRecorder
    ): JsonResponse {
        $payload = $request->validated();
        $outputDisk = $payload['output_disk'] ?? $chunk->output_disk ?? $chunk->input_disk;
        $engineServerId = (int) ($chunk->engine_server_id ?? 0);
        $totalCount = (int) ($payload['email_count'] ?? $chunk->email_count ?? 0);
        $processingStage = $this->normalizeProcessingStage((string) ($chunk->processing_stage ?? ''));
        $completedByWorkerId = trim((string) ($chunk->assigned_worker_id ?? ''));
        $lastWorkerIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($chunk->last_worker_ids) ? $chunk->last_worker_ids : []
        ))));
        if ($completedByWorkerId !== '') {
            $lastWorkerIds[] = $completedByWorkerId;
            $lastWorkerIds = array_values(array_unique($lastWorkerIds));
            if (count($lastWorkerIds) > 5) {
                $lastWorkerIds = array_slice($lastWorkerIds, -5);
            }
        }

        if ($chunk->status === 'completed') {
            if (! $this->payloadMatches($chunk, $payload, $outputDisk)) {
                return response()->json([
                    'message' => 'Chunk completion payload does not match existing data.',
                ], 409);
            }

            return response()->json([
                'data' => [
                    'chunk_id' => (string) $chunk->id,
                    'status' => $chunk->status,
                ],
            ]);
        }

        if ($chunk->status === 'failed') {
            return response()->json([
                'message' => 'Chunk is already failed.',
            ], 409);
        }

        $screeningPlan = [
            'candidate_count' => 0,
            'hard_invalid_count' => 0,
            'probe_chunk_id' => null,
            'probe_chunk_ids' => [],
            'probe_chunk_count' => 0,
        ];
        $probeStage = ['enabled' => false, 'reason' => null];

        if ($chunk->job && $processingStage === 'screening') {
            $probeStage = SmtpProbeStage::evaluate();

            if ($probeStage['enabled']) {
                try {
                    $planningChunk = clone $chunk;
                    $planningChunk->forceFill([
                        'output_disk' => $outputDisk,
                        'valid_key' => $payload['valid_key'],
                        'invalid_key' => $payload['invalid_key'],
                        'risky_key' => $payload['risky_key'],
                        'email_count' => $payload['email_count'] ?? $chunk->email_count,
                        'valid_count' => $payload['valid_count'] ?? $chunk->valid_count,
                        'invalid_count' => $payload['invalid_count'] ?? $chunk->invalid_count,
                        'risky_count' => $payload['risky_count'] ?? $chunk->risky_count,
                    ]);

                    $screeningPlan = $probePlanner->plan($chunk->job, $planningChunk, $outputDisk);
                } catch (Throwable $exception) {
                    report($exception);

                    $chunk->job->addLog('screening_probe_handoff_failed', 'Screening-to-probe handoff failed.', [
                        'chunk_id' => (string) $chunk->id,
                        'error' => $exception->getMessage(),
                    ], $request->user()?->id);

                    return response()->json([
                        'message' => 'Screening handoff failed. Retry this chunk completion request.',
                    ], 503);
                }
            } else {
                $chunk->job->addLog('screening_probe_skipped', 'SMTP probe handoff skipped.', [
                    'chunk_id' => (string) $chunk->id,
                    'reason' => $probeStage['reason'],
                ], $request->user()?->id);
            }
        }

        $chunk->update([
            'status' => 'completed',
            'output_disk' => $outputDisk,
            'valid_key' => $payload['valid_key'],
            'invalid_key' => $payload['invalid_key'],
            'risky_key' => $payload['risky_key'],
            'email_count' => $payload['email_count'] ?? $chunk->email_count,
            'valid_count' => $payload['valid_count'] ?? $chunk->valid_count,
            'invalid_count' => $payload['invalid_count'] ?? $chunk->invalid_count,
            'risky_count' => $payload['risky_count'] ?? $chunk->risky_count,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'claim_token' => null,
            'engine_server_id' => null,
            'assigned_worker_id' => null,
            'last_worker_ids' => $lastWorkerIds,
        ]);

        if ($chunk->job) {
            if ($processingStage === 'screening') {
                $chunk->job->addLog('screening_completed', 'Screening stage completed.', [
                    'chunk_id' => (string) $chunk->id,
                    'probe_candidate_count' => $screeningPlan['candidate_count'],
                    'hard_invalid_count' => $screeningPlan['hard_invalid_count'],
                    'probe_chunk_id' => $screeningPlan['probe_chunk_id'],
                    'probe_chunk_ids' => $screeningPlan['probe_chunk_ids'] ?? [],
                    'probe_chunk_count' => $screeningPlan['probe_chunk_count'] ?? 0,
                    'probe_handoff_enabled' => (bool) $probeStage['enabled'],
                ], $request->user()?->id);

                $screeningMetrics = [
                    'screening_total_count' => max(
                        0,
                        (int) (($chunk->job->metrics?->screening_total_count ?? 0) + (int) ($payload['email_count'] ?? 0))
                    ),
                ];

                if ($probeStage['enabled']) {
                    $screeningMetrics['probe_candidate_count'] = max(
                        0,
                        (int) (($chunk->job->metrics?->probe_candidate_count ?? 0) + ($screeningPlan['candidate_count'] ?? 0))
                    );
                }

                $metricsRecorder->recordPhase($chunk->job, 'verify_chunks', $screeningMetrics);
            } else {
                $retryResult = $retryPlanner->plan($chunk->job, $chunk->fresh(), $outputDisk);

                $reputationRecorder->record(
                    $engineServerId,
                    $chunk,
                    $totalCount,
                    (int) ($retryResult['tempfail_count'] ?? 0)
                );

                $probeUnknownCount = (int) ($payload['risky_count'] ?? $chunk->risky_count ?? 0);
                $metricsRecorder->recordPhase($chunk->job, 'verify_chunks', [
                    'probe_completed_count' => max(
                        0,
                        (int) (($chunk->job->metrics?->probe_completed_count ?? 0) + $totalCount)
                    ),
                    'probe_unknown_count' => max(
                        0,
                        (int) (($chunk->job->metrics?->probe_unknown_count ?? 0) + $probeUnknownCount)
                    ),
                ]);

                if (($retryResult['retry_count'] ?? 0) > 0) {
                    $chunk->job->addLog('tempfail_retry_queued', 'Tempfail emails queued for retry.', [
                        'chunk_id' => (string) $chunk->id,
                        'retry_count' => $retryResult['retry_count'],
                        'retry_chunk_id' => $retryResult['retry_chunk_id'],
                    ], $request->user()?->id);
                }
            }
        }

        $chunk->refresh();

        $chunk->job?->addLog('chunk_completed', 'Chunk completed.', [
            'chunk_id' => (string) $chunk->id,
            'chunk_no' => $chunk->chunk_no,
            'output_disk' => $chunk->output_disk,
            'valid_key' => $chunk->valid_key,
            'invalid_key' => $chunk->invalid_key,
            'risky_key' => $chunk->risky_key,
            'email_count' => $chunk->email_count,
            'valid_count' => $chunk->valid_count,
            'invalid_count' => $chunk->invalid_count,
            'risky_count' => $chunk->risky_count,
        ], $request->user()?->id);

        if ($chunk->job) {
            FinalizeVerificationJob::dispatch($chunk->job->id);
        }

        return response()->json([
            'data' => [
                'chunk_id' => (string) $chunk->id,
                'status' => $chunk->status,
            ],
        ]);
    }

    private function payloadMatches(VerificationJobChunk $chunk, array $payload, ?string $outputDisk): bool
    {
        $pairs = [
            'output_disk' => $outputDisk,
            'valid_key' => $payload['valid_key'] ?? null,
            'invalid_key' => $payload['invalid_key'] ?? null,
            'risky_key' => $payload['risky_key'] ?? null,
            'email_count' => $payload['email_count'] ?? null,
            'valid_count' => $payload['valid_count'] ?? null,
            'invalid_count' => $payload['invalid_count'] ?? null,
            'risky_count' => $payload['risky_count'] ?? null,
        ];

        foreach ($pairs as $field => $value) {
            if ($value === null) {
                continue;
            }

            if ((string) $chunk->{$field} !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private function normalizeProcessingStage(string $value): string
    {
        $value = strtolower(trim($value));

        return $value === 'smtp_probe' ? 'smtp_probe' : 'screening';
    }
}
