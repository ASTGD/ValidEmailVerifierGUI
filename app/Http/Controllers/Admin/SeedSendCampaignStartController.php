<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartSeedSendCampaignRequest;
use App\Models\SeedSendConsent;
use App\Models\VerificationJob;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class SeedSendCampaignStartController extends Controller
{
    public function __invoke(
        StartSeedSendCampaignRequest $request,
        VerificationJob $job,
        SeedSendCampaignService $campaignService
    ): RedirectResponse {
        $consentId = (int) $request->validated('consent_id');
        $consent = SeedSendConsent::query()
            ->where('id', $consentId)
            ->where('verification_job_id', $job->id)
            ->firstOrFail();

        try {
            $campaignService->startCampaign($job, $consent, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'seed_send' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'SG6 campaign queued.');
    }
}
