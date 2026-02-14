<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Http\Requests\Verifier\ReviewSmtpPolicySuggestionRequest;
use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicySuggestion;
use Illuminate\Http\JsonResponse;

class VerifierPolicySuggestionReviewController
{
    public function __invoke(ReviewSmtpPolicySuggestionRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $suggestion = SmtpPolicySuggestion::query()->find($payload['suggestion_id']);
        if (! $suggestion) {
            abort(404);
        }

        $reviewer = trim((string) ($request->user()?->email ?? 'verifier-service'));
        $status = trim((string) $payload['status']);

        $suggestion->fill([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer,
            'review_notes' => is_array($payload['review_notes'] ?? null) ? $payload['review_notes'] : null,
        ]);
        $suggestion->save();

        SmtpPolicyActionAudit::query()->create([
            'action' => 'suggestion_review',
            'policy_version' => null,
            'provider' => $suggestion->provider,
            'source' => 'manual',
            'actor' => $reviewer,
            'result' => 'success',
            'context' => [
                'suggestion_id' => $suggestion->id,
                'status' => $status,
                'review_notes' => $suggestion->review_notes,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'suggestion_id' => $suggestion->id,
                'provider' => $suggestion->provider,
                'status' => $suggestion->status,
                'reviewed_at' => $suggestion->reviewed_at?->toIso8601String(),
                'reviewed_by' => $suggestion->reviewed_by,
            ],
        ]);
    }
}
