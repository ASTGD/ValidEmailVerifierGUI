<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Contracts\EngineStorageUrlSigner;
use App\Models\VerificationJobChunk;
use Illuminate\Http\JsonResponse;

class VerifierChunkInputUrlController
{
    public function __invoke(VerificationJobChunk $chunk, EngineStorageUrlSigner $signer): JsonResponse
    {
        $disk = $chunk->input_disk ?: ($chunk->job?->input_disk);
        $disk = $disk ?: (string) (config('verifier.storage_disk') ?: config('filesystems.default'));
        $expiry = (int) config('engine.signed_url_expiry_seconds', 300);

        $url = $signer->temporaryDownloadUrl($disk, $chunk->input_key, $expiry);

        return response()->json([
            'data' => [
                'disk' => $disk,
                'key' => $chunk->input_key,
                'url' => $url,
                'expires_in' => $expiry,
            ],
        ]);
    }
}
