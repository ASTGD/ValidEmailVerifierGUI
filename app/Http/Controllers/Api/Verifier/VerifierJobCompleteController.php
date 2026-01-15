<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Enums\VerificationJobStatus;
use App\Http\Requests\Verifier\CompleteJobRequest;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Http\JsonResponse;

class VerifierJobCompleteController
{
    public function __invoke(CompleteJobRequest $request, VerificationJob $job, JobStorage $storage): JsonResponse
    {
        if ($job->status === VerificationJobStatus::Completed) {
            return response()->json([
                'message' => 'Job is already completed.',
            ], 409);
        }

        if ($job->status === VerificationJobStatus::Failed) {
            return response()->json([
                'message' => 'Job is already failed.',
            ], 409);
        }

        $engineServerId = $job->engine_server_id;

        if (! $engineServerId && $request->filled('engine_server_id')) {
            $engineServerId = $request->integer('engine_server_id');
        }

        $job->update([
            'status' => VerificationJobStatus::Completed,
            'output_disk' => $request->input('output_disk', $storage->disk()),
            'output_key' => $request->input('output_key'),
            'engine_server_id' => $engineServerId,
            'claim_expires_at' => null,
            'claim_token' => null,
            'total_emails' => $request->input('total_emails'),
            'valid_count' => $request->input('valid_count'),
            'invalid_count' => $request->input('invalid_count'),
            'risky_count' => $request->input('risky_count'),
            'unknown_count' => $request->input('unknown_count'),
            'started_at' => $job->started_at ?: now(),
            'finished_at' => now(),
            'error_message' => null,
        ]);

        $job->addLog(
            'completed_via_engine_api',
            'Job completed via verifier API.',
            [
                'engine_server_id' => $job->engine_server_id,
                'output_disk' => $job->output_disk,
                'output_key' => $job->output_key,
                'total_emails' => $job->total_emails,
                'valid_count' => $job->valid_count,
                'invalid_count' => $job->invalid_count,
                'risky_count' => $job->risky_count,
                'unknown_count' => $job->unknown_count,
            ],
            $request->user()?->id
        );

        return response()->json([
            'data' => [
                'id' => (string) $job->id,
                'status' => $job->status->value,
            ],
        ]);
    }
}
