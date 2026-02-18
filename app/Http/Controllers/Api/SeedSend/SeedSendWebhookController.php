<?php

namespace App\Http\Controllers\Api\SeedSend;

use App\Http\Controllers\Controller;
use App\Jobs\IngestSeedSendEventJob;
use App\Services\SeedSend\Providers\SeedSendProviderManager;
use App\Services\SeedSend\SeedSendWebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeedSendWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        SeedSendProviderManager $providerManager,
        SeedSendWebhookSignatureValidator $validator
    ): JsonResponse {
        if (! (bool) config('seed_send.enabled', false)) {
            return response()->json(['message' => 'Seed send is disabled.'], 404);
        }

        if (! $providerManager->isEnabled($provider)) {
            return response()->json(['message' => 'Provider is disabled.'], 422);
        }

        if (! $validator->validate($request, $provider)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Invalid webhook payload.'], 422);
        }

        $normalizedEvents = $providerManager->normalizeWebhookEvents($provider, $payload);
        if ($normalizedEvents === []) {
            return response()->json([
                'message' => 'Unable to normalize webhook payload.',
            ], 422);
        }

        $allMappingsValid = collect($normalizedEvents)
            ->every(fn (array $event): bool => $this->hasValidMappingKey($event));

        if (! $allMappingsValid) {
            return response()->json([
                'message' => 'Invalid webhook mapping key. Provide provider_message_id or campaign_id + valid email.',
            ], 422);
        }

        foreach ($normalizedEvents as $event) {
            IngestSeedSendEventJob::dispatch(strtolower(trim($provider)), $event);
        }

        return response()->json([
            'message' => 'accepted',
            'accepted_events' => count($normalizedEvents),
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasValidMappingKey(array $payload): bool
    {
        $providerMessageId = trim((string) ($payload['provider_message_id'] ?? $payload['message_id'] ?? ''));
        if ($providerMessageId !== '') {
            return true;
        }

        $campaignId = trim((string) ($payload['campaign_id'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        if ($campaignId === '' || $email === '') {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
