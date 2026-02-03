<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Jobs\FinalizeVerificationJob;
use App\Http\Requests\Verifier\ChunkCompleteRequest;
use App\Models\VerificationJobChunk;
use Illuminate\Http\JsonResponse;
use App\Services\EngineServerReputationRecorder;
use App\Services\TempfailRetryPlanner;

class VerifierChunkCompleteController
{
    public function __invoke(
        ChunkCompleteRequest $request,
        VerificationJobChunk $chunk,
        TempfailRetryPlanner $retryPlanner,
        EngineServerReputationRecorder $reputationRecorder
    ): JsonResponse
    {
        $payload = $request->validated();
        $outputDisk = $payload['output_disk'] ?? $chunk->output_disk ?? $chunk->input_disk;
        $engineServerId = (int) ($chunk->engine_server_id ?? 0);
        $totalCount = (int) ($payload['email_count'] ?? $chunk->email_count ?? 0);

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
        ]);

        if ($chunk->job) {
            $retryResult = $retryPlanner->plan($chunk->job, $chunk->fresh(), $outputDisk);

            $reputationRecorder->record(
                $engineServerId,
                $chunk,
                $totalCount,
                (int) ($retryResult['tempfail_count'] ?? 0)
            );

            if (($retryResult['retry_count'] ?? 0) > 0) {
                $chunk->job->addLog('tempfail_retry_queued', 'Tempfail emails queued for retry.', [
                    'chunk_id' => (string) $chunk->id,
                    'retry_count' => $retryResult['retry_count'],
                    'retry_chunk_id' => $retryResult['retry_chunk_id'],
                ], $request->user()?->id);
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
}
