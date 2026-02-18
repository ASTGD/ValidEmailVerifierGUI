<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveSeedSendConsentRequest;
use App\Models\SeedSendConsent;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendConsentApproveController extends Controller
{
    public function __invoke(
        ApproveSeedSendConsentRequest $request,
        SeedSendConsent $consent,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        try {
            $campaignService->approveConsent($consent, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'SG6 consent approved.');
    }
}
