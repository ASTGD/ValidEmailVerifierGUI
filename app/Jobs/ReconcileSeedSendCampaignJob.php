<?php

namespace App\Jobs;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendRecipient;
use App\Services\SeedSend\SeedSendCreditLedgerService;
use App\Services\SeedSend\SeedSendEvidenceReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileSeedSendCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(public string $campaignId)
    {
        $this->connection = 'redis_seed_send_reconcile';
        $this->queue = (string) config('queue.connections.redis_seed_send_reconcile.queue', 'seed_send_reconcile');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:seed_send_reconcile',
            'seed_send_campaign:'.$this->campaignId,
        ];
    }

    public function handle(SeedSendCreditLedgerService $creditLedgerService, SeedSendEvidenceReportService $reportService): void
    {
        $campaign = SeedSendCampaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        if (in_array($campaign->status, [
            SeedSendCampaign::STATUS_COMPLETED,
            SeedSendCampaign::STATUS_CANCELLED,
            SeedSendCampaign::STATUS_FAILED,
        ], true)) {
            return;
        }

        if ($campaign->status === SeedSendCampaign::STATUS_PAUSED) {
            return;
        }

        $maxPendingAgeMinutes = max(1, (int) config('seed_send.reconcile.max_pending_age_minutes', 120));
        $pendingOrDispatchedCount = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [
                SeedSendRecipient::STATUS_PENDING,
                SeedSendRecipient::STATUS_DISPATCHING,
                SeedSendRecipient::STATUS_DISPATCHED,
            ])
            ->count();

        if ($pendingOrDispatchedCount > 0 && $campaign->started_at && $campaign->started_at->gt(now()->subMinutes($maxPendingAgeMinutes))) {
            static::dispatch($campaign->id)->delay(now()->addMinutes(10));

            return;
        }

        SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [
                SeedSendRecipient::STATUS_PENDING,
                SeedSendRecipient::STATUS_DISPATCHING,
                SeedSendRecipient::STATUS_DISPATCHED,
            ])
            ->update([
                'status' => SeedSendRecipient::STATUS_DEFERRED,
                'last_event_at' => now(),
                'updated_at' => now(),
            ]);

        $totals = SeedSendRecipient::query()
            ->selectRaw('status, count(*) as count')
            ->where('campaign_id', $campaign->id)
            ->groupBy('status')
            ->pluck('count', 'status');

        $delivered = (int) ($totals[SeedSendRecipient::STATUS_DELIVERED] ?? 0);
        $bounced = (int) ($totals[SeedSendRecipient::STATUS_BOUNCED] ?? 0);
        $deferred = (int) ($totals[SeedSendRecipient::STATUS_DEFERRED] ?? 0);
        $failed = (int) ($totals[SeedSendRecipient::STATUS_FAILED] ?? 0);
        $sent = $delivered + $bounced + $deferred + $failed;

        $campaign->update([
            'status' => SeedSendCampaign::STATUS_COMPLETED,
            'sent_count' => $sent,
            'delivered_count' => $delivered,
            'bounced_count' => $bounced,
            'deferred_count' => $deferred,
            'credits_used' => $sent * max(1, (int) config('seed_send.credits.per_recipient', 1)),
            'finished_at' => now(),
            'pause_reason' => null,
            'paused_at' => null,
        ]);

        $campaign->refresh();
        $creditLedgerService->settleCampaign($campaign, (int) $campaign->credits_used);

        try {
            $report = $reportService->storeCampaignReport($campaign);
            $campaign->update([
                'report_disk' => $report['disk'],
                'report_key' => $report['key'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('SG6 report generation failed during reconcile.', [
                'campaign_id' => $campaign->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $campaign->job?->addLog('seed_send_campaign_completed', 'SG6 campaign reconciled and completed.', [
            'seed_send_campaign_id' => $campaign->id,
            'sent_count' => $sent,
            'delivered_count' => $delivered,
            'bounced_count' => $bounced,
            'deferred_count' => $deferred,
        ]);
    }
}
