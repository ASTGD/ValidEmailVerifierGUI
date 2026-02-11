<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveSeedSendConsentRequest;
use App\Models\SeedSendConsent;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;

class SeedSendConsentApproveController extends Controller
{
    public function __invoke(
        ApproveSeedSendConsentRequest $request,
        SeedSendConsent $consent,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        $campaignService->approveConsent($consent, $request->user());

        return back()->with('status', 'SG6 consent approved.');
    }
}
