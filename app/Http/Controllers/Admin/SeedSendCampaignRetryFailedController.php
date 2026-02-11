<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RetrySeedSendCampaignRequest;
use App\Models\SeedSendCampaign;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendCampaignRetryFailedController extends Controller
{
    public function __invoke(
        RetrySeedSendCampaignRequest $request,
        SeedSendCampaign $campaign,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        try {
            $affected = $campaignService->retryDeferredOrFailedRecipients(
                $campaign,
                $request->user(),
                (int) $request->validated('max_recipients', 500)
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        if ($affected === 0) {
            return back()->with('status', 'No SG6 deferred/failed recipients found to retry.');
        }

        return back()->with('status', sprintf('Requeued %d SG6 recipients.', $affected));
    }
}
