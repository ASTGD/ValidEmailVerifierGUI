<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PauseSeedSendCampaignRequest;
use App\Models\SeedSendCampaign;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendCampaignPauseController extends Controller
{
    public function __invoke(
        PauseSeedSendCampaignRequest $request,
        SeedSendCampaign $campaign,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        $action = strtolower(trim((string) $request->input('action', 'pause')));

        try {
            if ($action === 'resume') {
                $campaignService->resumeCampaign($campaign, $request->user());

                return back()->with('status', 'SG6 campaign resumed.');
            }

            $campaignService->pauseCampaign($campaign, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'SG6 campaign paused.');
    }
}
