<?php

namespace App\Filament\Widgets;

use App\Services\SeedSend\SeedSendCampaignHealthService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsSeedSendHealthOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $summary = app(SeedSendCampaignHealthService::class)->summary();

        $issues = collect((array) ($summary['issues'] ?? []));
        $primaryIssue = $issues->first();

        return [
            Stat::make('SG6 Status', ucfirst((string) ($summary['status'] ?? 'unknown')))
                ->description(sprintf(
                    'Running %d | Queued %d | Paused %d',
                    (int) ($summary['running_campaigns'] ?? 0),
                    (int) ($summary['queued_campaigns'] ?? 0),
                    (int) ($summary['paused_campaigns'] ?? 0)
                ))
                ->color($this->statusColor((string) ($summary['status'] ?? 'unknown'))),
            Stat::make('Webhook Lag', $this->webhookLagLabel($summary['webhook_lag_seconds'] ?? null))
                ->description($this->webhookLagDescription($summary['last_event_at'] ?? null))
                ->color($this->webhookLagColor($summary['webhook_lag_seconds'] ?? null)),
            Stat::make('Top SG6 Issue', $primaryIssue['code'] ?? 'none')
                ->description($primaryIssue['message'] ?? 'No active SG6 incidents.')
                ->color(($primaryIssue['severity'] ?? '') === 'critical' ? 'danger' : (($primaryIssue['severity'] ?? '') === 'warning' ? 'warning' : 'success')),
        ];
    }

    private function statusColor(string $status): string
    {
        return match (strtolower($status)) {
            'critical' => 'danger',
            'warning' => 'warning',
            'healthy' => 'success',
            default => 'gray',
        };
    }

    private function webhookLagLabel(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'n/a';
        }

        return sprintf('%ds', (int) $value);
    }

    private function webhookLagDescription(mixed $lastEventAt): string
    {
        if (! is_string($lastEventAt) || trim($lastEventAt) === '') {
            return 'No webhook events recorded yet';
        }

        return sprintf('Last event at %s', $lastEventAt);
    }

    private function webhookLagColor(mixed $lag): string
    {
        if (! is_numeric($lag)) {
            return 'gray';
        }

        $threshold = max(60, (int) config('seed_send.health.max_webhook_lag_seconds', 300));

        return ((int) $lag > $threshold) ? 'danger' : 'success';
    }
}
