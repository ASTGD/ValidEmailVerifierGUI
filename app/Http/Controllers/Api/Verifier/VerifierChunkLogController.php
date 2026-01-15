<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Http\Requests\Verifier\ChunkLogRequest;
use App\Models\VerificationJobChunk;
use Illuminate\Http\JsonResponse;

class VerifierChunkLogController
{
    public function __invoke(ChunkLogRequest $request, VerificationJobChunk $chunk): JsonResponse
    {
        $payload = $request->validated();

        $chunk->job?->addLog(
            $payload['event'],
            $payload['message'] ?? null,
            [
                'chunk_id' => (string) $chunk->id,
                'chunk_no' => $chunk->chunk_no,
                'level' => $payload['level'],
                'context' => $payload['context'] ?? null,
            ],
            $request->user()?->id
        );

        return response()->json([
            'data' => [
                'chunk_id' => (string) $chunk->id,
                'status' => $chunk->status,
            ],
        ]);
    }
}
