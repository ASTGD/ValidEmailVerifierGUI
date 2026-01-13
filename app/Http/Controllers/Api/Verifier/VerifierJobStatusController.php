<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Enums\VerificationJobStatus;
use App\Http\Requests\Verifier\UpdateJobStatusRequest;
use App\Models\VerificationJob;
use Illuminate\Http\JsonResponse;

class VerifierJobStatusController
{
    public function __invoke(UpdateJobStatusRequest $request, VerificationJob $job): JsonResponse
    {
        $status = VerificationJobStatus::from($request->string('status')->toString());
        $previousStatus = $job->status;

        if ($status === VerificationJobStatus::Processing && $job->status !== VerificationJobStatus::Pending) {
            return response()->json([
                'message' => 'Job is not pending.',
            ], 409);
        }

        if ($status === VerificationJobStatus::Failed
            && ! in_array($job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true)
        ) {
            return response()->json([
                'message' => 'Job cannot be marked failed from the current status.',
            ], 409);
        }

        $attributes = [
            'status' => $status,
        ];

        if ($status === VerificationJobStatus::Processing) {
            $attributes['started_at'] = $job->started_at ?: now();
        }

        if ($status === VerificationJobStatus::Failed) {
            $attributes['error_message'] = $request->input('error_message');
            $attributes['failure_source'] = VerificationJob::FAILURE_SOURCE_ENGINE;
            $attributes['failure_code'] = 'engine_error';
            $attributes['finished_at'] = now();
        }

        $job->update($attributes);

        $context = [
            'from' => $previousStatus->value,
            'to' => $job->status->value,
        ];

        if ($status === VerificationJobStatus::Failed) {
            $context['error_message'] = $request->input('error_message');
        }

        $job->addLog(
            'status_changed',
            'Status updated via verifier API.',
            $context,
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
