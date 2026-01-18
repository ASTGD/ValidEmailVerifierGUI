<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Contracts\EngineStorageUrlSigner;
use App\Models\VerificationJobChunk;
use App\Services\JobStorage;
use Illuminate\Http\JsonResponse;

class VerifierChunkOutputUrlsController
{
    public function __invoke(VerificationJobChunk $chunk, EngineStorageUrlSigner $signer, JobStorage $storage): JsonResponse
    {
        if (! $chunk->job) {
            return response()->json([
                'message' => 'Chunk job not found.',
            ], 409);
        }

        $disk = $chunk->output_disk ?: ($chunk->job?->output_disk ?: $storage->disk());
        $expiry = (int) config('engine.signed_url_expiry_seconds', 300);

        $validKey = $storage->chunkOutputKey($chunk->job, $chunk->chunk_no, 'valid');
        $invalidKey = $storage->chunkOutputKey($chunk->job, $chunk->chunk_no, 'invalid');
        $riskyKey = $storage->chunkOutputKey($chunk->job, $chunk->chunk_no, 'risky');

        return response()->json([
            'data' => [
                'disk' => $disk,
                'expires_in' => $expiry,
                'targets' => [
                    'valid' => [
                        'key' => $validKey,
                        'url' => $signer->temporaryUploadUrl($disk, $validKey, $expiry, 'text/csv'),
                    ],
                    'invalid' => [
                        'key' => $invalidKey,
                        'url' => $signer->temporaryUploadUrl($disk, $invalidKey, $expiry, 'text/csv'),
                    ],
                    'risky' => [
                        'key' => $riskyKey,
                        'url' => $signer->temporaryUploadUrl($disk, $riskyKey, $expiry, 'text/csv'),
                    ],
                ],
            ],
        ]);
    }
}
