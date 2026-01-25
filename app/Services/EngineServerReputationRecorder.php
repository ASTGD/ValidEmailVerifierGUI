<?php

namespace App\Services;

use App\Models\EngineServerReputationSample;
use App\Models\VerificationJobChunk;

class EngineServerReputationRecorder
{
    public function record(int $engineServerId, VerificationJobChunk $chunk, int $total, int $tempfailCount): void
    {
        if ($engineServerId <= 0) {
            return;
        }

        EngineServerReputationSample::query()->updateOrCreate(
            ['verification_job_chunk_id' => $chunk->id],
            [
                'engine_server_id' => $engineServerId,
                'total_count' => max(0, $total),
                'tempfail_count' => max(0, $tempfailCount),
                'recorded_at' => now(),
            ]
        );
    }
}
