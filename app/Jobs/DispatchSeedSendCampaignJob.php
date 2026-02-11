<?php

namespace App\Jobs;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\SeedSendRecipient;
use App\Services\SeedSend\Providers\SeedSendProviderManager;
use App\Services\SeedSend\SeedSendCampaignGuardrails;
use App\Services\SeedSend\SeedSendCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchSeedSendCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 2;

    public bool $failOnTimeout = true;

    public function __construct(public string $campaignId)
    {
        $this->connection = 'redis_seed_send_dispatch';
        $this->queue = (string) config('queue.connections.redis_seed_send_dispatch.queue', 'seed_send_dispatch');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:seed_send_dispatch',
            'seed_send_campaign:'.$this->campaignId,
        ];
    }

    public function handle(
        SeedSendProviderManager $providerManager,
        SeedSendCampaignGuardrails $guardrails,
        SeedSendCampaignService $campaignService
    ): void {
        $campaignForGuard = SeedSendCampaign::query()
            ->with('consent')
            ->find($this->campaignId);

        if ($campaignForGuard && $this->isConsentRevokedOrExpired($campaignForGuard)) {
            $campaignService->cancelCampaignForSafety($campaignForGuard, 'consent_revoked_or_expired');

            return;
        }

        $batchSize = max(1, (int) config('seed_send.dispatch.batch_size', 25));
        $blockedByConsent = false;
        $claimedRecipientIds = DB::transaction(function () use ($batchSize, &$blockedByConsent): array {
            $campaign = SeedSendCampaign::query()
                ->where('id', $this->campaignId)
                ->lockForUpdate()
                ->first();

            if (! $campaign) {
                return [];
            }

            $campaign->load('consent');
            if ($this->isConsentRevokedOrExpired($campaign)) {
                $blockedByConsent = true;

                return [];
            }

            if (in_array($campaign->status, [
                SeedSendCampaign::STATUS_PAUSED,
                SeedSendCampaign::STATUS_COMPLETED,
                SeedSendCampaign::STATUS_FAILED,
                SeedSendCampaign::STATUS_CANCELLED,
            ], true)) {
                return [];
            }

            if ($campaign->status === SeedSendCampaign::STATUS_QUEUED) {
                $campaign->update([
                    'status' => SeedSendCampaign::STATUS_RUNNING,
                    'started_at' => $campaign->started_at ?: now(),
                ]);
            }

            if ($campaign->status !== SeedSendCampaign::STATUS_RUNNING) {
                return [];
            }

            $recipientIds = SeedSendRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', SeedSendRecipient::STATUS_PENDING)
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($value): int => (int) $value)
                ->all();

            if ($recipientIds === []) {
                return [];
            }

            SeedSendRecipient::query()
                ->whereIn('id', $recipientIds)
                ->where('status', SeedSendRecipient::STATUS_PENDING)
                ->update([
                    'status' => SeedSendRecipient::STATUS_DISPATCHING,
                    'attempt_count' => DB::raw('attempt_count + 1'),
                    'last_attempt_at' => now(),
                    'updated_at' => now(),
                ]);

            return $recipientIds;
        });

        $campaign = SeedSendCampaign::query()
            ->with('consent')
            ->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        if ($blockedByConsent || $this->isConsentRevokedOrExpired($campaign)) {
            $campaignService->cancelCampaignForSafety($campaign, 'consent_revoked_or_expired');

            return;
        }

        if ($claimedRecipientIds === []) {
            ReconcileSeedSendCampaignJob::dispatch($campaign->id)
                ->delay(now()->addMinutes(max(1, (int) config('seed_send.reconcile.delay_minutes', 30))));

            return;
        }

        $claimedRecipients = SeedSendRecipient::query()
            ->whereIn('id', $claimedRecipientIds)
            ->where('campaign_id', $campaign->id)
            ->where('status', SeedSendRecipient::STATUS_DISPATCHING)
            ->orderBy('id')
            ->get();

        if ($claimedRecipients->isEmpty()) {
            return;
        }

        $campaign->refresh()->load('consent');
        if ($this->isConsentRevokedOrExpired($campaign)) {
            $campaignService->cancelCampaignForSafety($campaign, 'consent_revoked_or_expired');

            return;
        }

        $provider = $providerManager->provider($campaign->provider);

        foreach ($claimedRecipients as $recipient) {
            $campaign->refresh()->load('consent');
            if ($this->isConsentRevokedOrExpired($campaign)) {
                $campaignService->cancelCampaignForSafety($campaign, 'consent_revoked_or_expired');

                return;
            }

            try {
                $result = $provider->dispatch($campaign, $recipient);

                SeedSendRecipient::query()
                    ->where('id', $recipient->id)
                    ->where('status', SeedSendRecipient::STATUS_DISPATCHING)
                    ->update([
                        'status' => SeedSendRecipient::STATUS_DISPATCHED,
                        'provider_message_id' => (string) ($result['provider_message_id'] ?? $recipient->provider_message_id),
                        'provider_payload' => is_array($result['payload'] ?? null) ? $result['payload'] : null,
                    ]);
            } catch (Throwable $exception) {
                SeedSendRecipient::query()
                    ->where('id', $recipient->id)
                    ->where('status', SeedSendRecipient::STATUS_DISPATCHING)
                    ->update([
                        'status' => SeedSendRecipient::STATUS_DEFERRED,
                        'evidence_payload' => [
                            'dispatch_error' => $exception->getMessage(),
                        ],
                    ]);
            }
        }

        $this->refreshCampaignCounters($campaign->id);
        $campaign->refresh();
        $guardrails->evaluateAndApply($campaign);

        if ($campaign->status !== SeedSendCampaign::STATUS_RUNNING) {
            ReconcileSeedSendCampaignJob::dispatch($campaign->id)
                ->delay(now()->addMinutes(max(1, (int) config('seed_send.reconcile.delay_minutes', 30))));

            return;
        }

        $pendingCount = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', SeedSendRecipient::STATUS_PENDING)
            ->count();

        if ($pendingCount > 0) {
            static::dispatch($campaign->id)
                ->delay(now()->addSeconds(max(1, (int) config('seed_send.dispatch.delay_seconds', 5))));

            return;
        }

        ReconcileSeedSendCampaignJob::dispatch($campaign->id)
            ->delay(now()->addMinutes(max(1, (int) config('seed_send.reconcile.delay_minutes', 30))));
    }

    private function refreshCampaignCounters(string $campaignId): void
    {
        $totals = SeedSendRecipient::query()
            ->selectRaw('status, count(*) as count')
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->pluck('count', 'status');

        $delivered = (int) ($totals[SeedSendRecipient::STATUS_DELIVERED] ?? 0);
        $bounced = (int) ($totals[SeedSendRecipient::STATUS_BOUNCED] ?? 0);
        $deferred = (int) ($totals[SeedSendRecipient::STATUS_DEFERRED] ?? 0);
        $dispatched = (int) ($totals[SeedSendRecipient::STATUS_DISPATCHED] ?? 0);
        $failed = (int) ($totals[SeedSendRecipient::STATUS_FAILED] ?? 0);

        SeedSendCampaign::query()
            ->where('id', $campaignId)
            ->update([
                'sent_count' => $dispatched + $delivered + $bounced + $deferred + $failed,
                'delivered_count' => $delivered,
                'bounced_count' => $bounced,
                'deferred_count' => $deferred,
                'updated_at' => now(),
            ]);
    }

    private function isConsentRevokedOrExpired(SeedSendCampaign $campaign): bool
    {
        $consent = $campaign->consent;
        if (! $consent) {
            return true;
        }

        if ($consent->status === SeedSendConsent::STATUS_REVOKED || $consent->revoked_at !== null) {
            return true;
        }

        return $consent->expires_at !== null && $consent->expires_at->lte(now());
    }
}
