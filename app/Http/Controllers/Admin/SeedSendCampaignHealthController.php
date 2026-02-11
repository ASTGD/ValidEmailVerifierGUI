<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SeedSend\SeedSendCampaignHealthService;
use Illuminate\Http\JsonResponse;

class SeedSendCampaignHealthController extends Controller
{
    public function __invoke(SeedSendCampaignHealthService $healthService): JsonResponse
    {
        if (! (bool) config('seed_send.enabled', false)) {
            return response()->json([
                'status' => 'disabled',
                'message' => 'SG6 feature is disabled.',
            ], 404);
        }

        return response()->json($healthService->summary());
    }
}
