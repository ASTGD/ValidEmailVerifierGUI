<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SeedSendCampaign;
use App\Models\VerificationJob;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SeedSendReportDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, VerificationJob $job)
    {
        if (! (bool) config('seed_send.enabled', false)) {
            abort(404);
        }

        $this->authorize('download', $job);

        $campaignId = trim((string) $request->query('campaign_id', ''));

        $campaignQuery = $job->seedSendCampaigns()->whereNotNull('report_key');
        if ($campaignId !== '') {
            $campaignQuery->where('id', $campaignId);
        }

        /** @var SeedSendCampaign|null $campaign */
        $campaign = $campaignQuery->latest('created_at')->first();
        if (! $campaign || ! $campaign->report_key) {
            abort(404);
        }

        $disk = trim((string) ($campaign->report_disk ?: config('seed_send.reports.disk', config('filesystems.default'))));
        if ($disk === '' || ! Storage::disk($disk)->exists($campaign->report_key)) {
            abort(404);
        }

        $filename = sprintf('sg6-%s-%s-evidence.csv', $job->id, $campaign->id);

        return Storage::disk($disk)->download($campaign->report_key, $filename);
    }
}
