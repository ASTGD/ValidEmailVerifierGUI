<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSeedSendCampaignRequest;
use App\Models\SeedSendCampaign;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendCampaignCancelController extends Controller
{
    public function __invoke(
        CancelSeedSendCampaignRequest $request,
        SeedSendCampaign $campaign,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        try {
            $campaignService->cancelCampaign($campaign, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'SG6 campaign cancelled.');
    }
}
