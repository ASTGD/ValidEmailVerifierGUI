<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Http\Requests\Verifier\ChunkFailRequest;
use App\Models\VerificationJobChunk;
use Illuminate\Http\JsonResponse;

class VerifierChunkFailController
{
    public function __invoke(ChunkFailRequest $request, VerificationJobChunk $chunk): JsonResponse
    {
        if ($chunk->status === 'completed') {
            return response()->json([
                'message' => 'Chunk is already completed.',
            ], 409);
        }

        if ($chunk->status === 'failed') {
            return response()->json([
                'data' => [
                    'chunk_id' => (string) $chunk->id,
                    'status' => $chunk->status,
                ],
            ]);
        }

        $payload = $request->validated();
        $maxAttempts = (int) config('engine.max_attempts', 3);
        $attempts = $chunk->attempts + 1;
        $retryable = (bool) $payload['retryable'];

        $nextStatus = ($retryable && $attempts < $maxAttempts) ? 'pending' : 'failed';

        $chunk->update([
            'status' => $nextStatus,
            'attempts' => $attempts,
            'claimed_at' => null,
            'claim_expires_at' => null,
            'claim_token' => null,
            'engine_server_id' => null,
            'assigned_worker_id' => null,
        ]);

        $chunk->job?->addLog('chunk_failed', $payload['error_message'] ?? 'Chunk failed.', [
            'chunk_id' => (string) $chunk->id,
            'chunk_no' => $chunk->chunk_no,
            'attempts' => $attempts,
            'retryable' => $retryable,
            'status' => $nextStatus,
        ], $request->user()?->id);

        return response()->json([
            'data' => [
                'chunk_id' => (string) $chunk->id,
                'status' => $chunk->status,
                'attempts' => $chunk->attempts,
            ],
        ]);
    }
}
