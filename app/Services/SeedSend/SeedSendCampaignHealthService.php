<?php

namespace App\Services\SeedSend;

use App\Models\SeedSendCampaign;
use App\Models\SeedSendEvent;
use App\Services\SeedSend\Providers\SeedSendProviderManager;
use Carbon\CarbonImmutable;

class SeedSendCampaignHealthService
{
    public function __construct(private SeedSendProviderManager $providerManager) {}

    /**
     * @return array{
     *     status: string,
     *     checked_at: string,
     *     active_campaigns: int,
     *     running_campaigns: int,
     *     queued_campaigns: int,
     *     paused_campaigns: int,
     *     provider: array<string, mixed>,
     *     last_event_at: string|null,
     *     webhook_lag_seconds: int|null,
     *     issues: array<int, array{code: string, severity: string, message: string}>
     * }
     */
    public function summary(): array
    {
        $activeStatuses = [
            SeedSendCampaign::STATUS_QUEUED,
            SeedSendCampaign::STATUS_RUNNING,
            SeedSendCampaign::STATUS_PAUSED,
        ];

        $counts = SeedSendCampaign::query()
            ->selectRaw('status, count(*) as count')
            ->whereIn('status', $activeStatuses)
            ->groupBy('status')
            ->pluck('count', 'status');

        $runningCampaigns = (int) ($counts[SeedSendCampaign::STATUS_RUNNING] ?? 0);
        $queuedCampaigns = (int) ($counts[SeedSendCampaign::STATUS_QUEUED] ?? 0);
        $pausedCampaigns = (int) ($counts[SeedSendCampaign::STATUS_PAUSED] ?? 0);
        $activeCampaigns = $runningCampaigns + $queuedCampaigns + $pausedCampaigns;

        $lastEventAtRaw = SeedSendEvent::query()->max('created_at');
        $lastEventAt = $lastEventAtRaw ? CarbonImmutable::parse($lastEventAtRaw) : null;
        $webhookLagSeconds = $lastEventAt ? $lastEventAt->diffInSeconds(now()) : null;
        $maxWebhookLagSeconds = max(60, (int) config('seed_send.health.max_webhook_lag_seconds', 300));

        $issues = [];
        if ($activeCampaigns > 0 && $webhookLagSeconds === null) {
            $issues[] = [
                'code' => 'webhook_no_events',
                'severity' => 'warning',
                'message' => 'Active SG6 campaigns have no webhook events yet.',
            ];
        }

        if ($activeCampaigns > 0 && $webhookLagSeconds !== null && $webhookLagSeconds > $maxWebhookLagSeconds) {
            $issues[] = [
                'code' => 'webhook_lag',
                'severity' => 'critical',
                'message' => sprintf('Webhook event lag is %d seconds.', $webhookLagSeconds),
            ];
        }

        $status = 'healthy';
        if (collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'critical')) {
            $status = 'critical';
        } elseif ($issues !== []) {
            $status = 'warning';
        }

        $provider = $this->providerManager->defaultProvider();

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'active_campaigns' => $activeCampaigns,
            'running_campaigns' => $runningCampaigns,
            'queued_campaigns' => $queuedCampaigns,
            'paused_campaigns' => $pausedCampaigns,
            'provider' => [
                'name' => $provider,
                ...$this->providerManager->providerHealthMetadata($provider),
            ],
            'last_event_at' => $lastEventAt?->toIso8601String(),
            'webhook_lag_seconds' => $webhookLagSeconds,
            'issues' => $issues,
        ];
    }
}
