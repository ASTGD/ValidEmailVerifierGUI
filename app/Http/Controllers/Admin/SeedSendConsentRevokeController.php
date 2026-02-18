<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RevokeSeedSendConsentRequest;
use App\Models\SeedSendConsent;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendConsentRevokeController extends Controller
{
    public function __invoke(
        RevokeSeedSendConsentRequest $request,
        SeedSendConsent $consent,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        try {
            $campaignService->revokeConsent($consent, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'SG6 consent revoked.');
    }
}
