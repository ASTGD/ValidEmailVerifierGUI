<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Enums\VerificationJobStatus;
use App\Http\Requests\Verifier\ListJobsRequest;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Http\JsonResponse;

class VerifierJobsController
{
    public function index(ListJobsRequest $request, JobStorage $storage): JsonResponse
    {
        $status = $request->input('status', VerificationJobStatus::Pending->value);
        $limit = (int) $request->input('limit', 10);

        $jobs = VerificationJob::query()
            ->where('status', $status)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $payload = $jobs->map(function (VerificationJob $job) use ($storage) {
            return [
                'id' => (string) $job->id,
                'user_id' => $job->user_id,
                'status' => $job->status->value,
                'original_filename' => $job->original_filename,
                'input_disk' => $job->input_disk ?: $storage->disk(),
                'input_key' => $job->input_key,
                'output_disk' => $job->output_disk ?: $storage->disk(),
                'expected_output_key' => $storage->outputKey($job),
                'download_url' => route('api.verifier.jobs.download', $job),
                'created_at' => $job->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $payload,
        ]);
    }
}
