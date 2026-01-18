<?php

namespace App\Http\Controllers\Api\Feedback;

use App\Http\Requests\Feedback\StoreFeedbackOutcomesRequest;
use App\Models\EmailVerificationOutcomeIngestion;
use App\Services\EmailVerificationOutcomes\OutcomeIngestor;
use App\Support\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class FeedbackOutcomesController
{
    public function __invoke(StoreFeedbackOutcomesRequest $request, OutcomeIngestor $ingestor): JsonResponse
    {
        $payload = $request->validated();
        $maxItems = (int) config('engine.feedback_max_items_per_request', 500);
        $itemCount = count($payload['items'] ?? []);
        if ($maxItems > 0 && $itemCount > $maxItems) {
            return response()->json([
                'message' => 'Feedback items exceed the maximum allowed.',
            ], 422);
        }

        $defaultSource = $payload['source'] ?? 'api_feedback';
        $defaultObservedAt = isset($payload['observed_at'])
            ? Carbon::parse($payload['observed_at'])
            : now();

        $result = $ingestor->ingest(
            $payload['items'],
            $defaultSource,
            $defaultObservedAt,
            $request->user()?->id
        );

        EmailVerificationOutcomeIngestion::create([
            'type' => EmailVerificationOutcomeIngestion::TYPE_API,
            'source' => $defaultSource,
            'item_count' => $itemCount,
            'imported_count' => $result['imported'],
            'skipped_count' => $result['skipped'],
            'error_count' => $result['skipped'],
            'user_id' => $request->user()?->id,
            'token_name' => $request->user()?->currentAccessToken()?->name,
            'ip_address' => $request->ip(),
        ]);

        AdminAuditLogger::log('feedback_outcomes_ingested', null, [
            'source' => $defaultSource,
            'item_count' => $itemCount,
            'imported_count' => $result['imported'],
            'skipped_count' => $result['skipped'],
        ]);

        return response()->json([
            'data' => [
                'imported_count' => $result['imported'],
                'skipped_count' => $result['skipped'],
                'error_sample' => $result['errors'],
            ],
        ]);
    }
}
