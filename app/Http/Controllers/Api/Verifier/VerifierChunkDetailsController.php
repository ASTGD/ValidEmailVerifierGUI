<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Models\VerificationJobChunk;
use Illuminate\Http\JsonResponse;

class VerifierChunkDetailsController
{
    public function __invoke(VerificationJobChunk $chunk): JsonResponse
    {
        return response()->json([
            'data' => [
                'chunk_id' => (string) $chunk->id,
                'job_id' => (string) $chunk->verification_job_id,
                'chunk_no' => $chunk->chunk_no,
                'status' => $chunk->status,
                'attempts' => $chunk->attempts,
                'input' => [
                    'disk' => $chunk->input_disk,
                    'key' => $chunk->input_key,
                ],
                'output' => [
                    'disk' => $chunk->output_disk,
                    'valid_key' => $chunk->valid_key,
                    'invalid_key' => $chunk->invalid_key,
                    'risky_key' => $chunk->risky_key,
                ],
                'policy' => [
                    'lease_seconds' => (int) config('engine.lease_seconds', config('verifier.engine_claim_lease_seconds', 600)),
                    'max_attempts' => (int) config('engine.max_attempts', 3),
                    'signed_url_expiry_seconds' => (int) config('engine.signed_url_expiry_seconds', 300),
                ],
            ],
        ]);
    }
}
