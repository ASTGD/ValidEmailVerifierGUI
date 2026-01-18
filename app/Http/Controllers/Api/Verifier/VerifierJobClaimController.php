<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Enums\VerificationJobStatus;
use App\Http\Requests\Verifier\ClaimJobRequest;
use App\Models\VerificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifierJobClaimController
{
    public function __invoke(ClaimJobRequest $request, VerificationJob $job): JsonResponse
    {
        if ($job->status !== VerificationJobStatus::Pending) {
            return response()->json([
                'message' => 'Job is not pending.',
            ], 409);
        }

        $leaseSeconds = (int) $request->input('lease_seconds', config('verifier.engine_claim_lease_seconds', 600));
        $leaseSeconds = max(60, min($leaseSeconds, 3600));

        $now = now();
        $expiresAt = $now->copy()->addSeconds($leaseSeconds);
        $claimToken = (string) Str::uuid();

        $updates = [
            'status' => VerificationJobStatus::Processing->value,
            'started_at' => DB::raw("COALESCE(started_at, '{$now->toDateTimeString()}')"),
            'claimed_at' => $now,
            'claim_expires_at' => $expiresAt,
            'claim_token' => $claimToken,
            'engine_attempts' => DB::raw('COALESCE(engine_attempts, 0) + 1'),
        ];

        if ($request->filled('engine_server_id')) {
            $updates['engine_server_id'] = $request->integer('engine_server_id');
        }

        $updated = VerificationJob::query()
            ->whereKey($job->id)
            ->where('status', VerificationJobStatus::Pending->value)
            ->where(function ($query) use ($now) {
                $query->whereNull('claim_expires_at')
                    ->orWhere('claim_expires_at', '<', $now);
            })
            ->update($updates);

        if ($updated === 0) {
            return response()->json([
                'message' => 'Job is already claimed.',
            ], 409);
        }

        $job->refresh();

        $job->addLog(
            'claimed',
            'Job claimed via verifier API.',
            [
                'engine_server_id' => $job->engine_server_id,
                'claim_token' => $job->claim_token,
                'claim_expires_at' => $job->claim_expires_at?->toISOString(),
                'lease_seconds' => $leaseSeconds,
            ],
            $request->user()?->id
        );

        return response()->json([
            'data' => [
                'id' => (string) $job->id,
                'status' => $job->status->value,
                'engine_server_id' => $job->engine_server_id,
                'claimed_at' => $job->claimed_at?->toISOString(),
                'claim_expires_at' => $job->claim_expires_at?->toISOString(),
                'claim_token' => $job->claim_token,
            ],
        ]);
    }
}
