<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SeedSendConsentRequest;
use App\Models\VerificationJob;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;

class SeedSendConsentRequestController extends Controller
{
    public function __invoke(
        SeedSendConsentRequest $request,
        VerificationJob $job,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        $campaignService->requestConsent($job, $request->user(), $request->validated('scope'));

        return back()->with('status', 'SG6 consent request submitted. Admin approval is required before campaign start.');
    }
}
