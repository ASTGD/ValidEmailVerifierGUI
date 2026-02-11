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

        IngestSeedSendEventJob::dispatch(strtolower(trim($provider)), $payload);

        return response()->json([
            'message' => 'accepted',
        ], 202);
    }
}
