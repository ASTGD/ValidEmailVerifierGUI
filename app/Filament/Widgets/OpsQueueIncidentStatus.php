<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OpsQueueIncidentStatus extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $report = $this->latestReport();

        if (! $report) {
            return [
                Stat::make('Queue Incident State', 'Unknown')
                    ->description('No health checks have run yet.')
                    ->color('gray'),
                Stat::make('Top Incidents', 'None')
                    ->description('Run `php artisan ops:queue-health` to initialize data.')
                    ->color('gray'),
                Stat::make('Last Check', 'Never')
                    ->description('No timestamp available')
                    ->color('gray'),
            ];
        }

        $status = strtolower((string) ($report['status'] ?? 'unknown'));
        $issues = collect((array) ($report['issues'] ?? []))->take(3)->values();

        $topIssueLabel = $issues->isEmpty() ? 'None' : $issues->first()['title'];
        $topIssueDetail = $issues->isEmpty()
            ? 'No active incidents'
            : $issues
                ->map(fn (array $issue): string => sprintf(
                    '[%s] %s',
                    strtoupper((string) ($issue['severity'] ?? 'warning')),
                    (string) ($issue['title'] ?? 'Issue')
                ))
                ->implode(' | ');

        return [
            Stat::make('Queue Incident State', ucfirst($status))
                ->description(sprintf(
                    'Critical: %d | Warning: %d',
                    (int) data_get($report, 'summary.critical', 0),
                    (int) data_get($report, 'summary.warning', 0)
                ))
                ->color($this->statusColor($status)),
            Stat::make('Top Incidents', $topIssueLabel)
                ->description($topIssueDetail)
                ->color($issues->isEmpty() ? 'success' : 'warning'),
            Stat::make('Last Check', $this->lastCheckLabel($report['checked_at'] ?? null))
                ->description($this->lastCheckDescription($report['checked_at'] ?? null))
                ->color('gray'),
        ];
    }

    /**
     * @return array{status?: string, issues?: array<int, array<string, mixed>>, summary?: array{critical?: int, warning?: int}, checked_at?: string}|null
     */
    private function latestReport(): ?array
    {
        $report = Cache::get((string) config('queue_health.report_cache_key', 'queue_health:last_report'));

        return is_array($report) ? $report : null;
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'critical' => 'danger',
            'warning' => 'warning',
            'healthy' => 'success',
            default => 'gray',
        };
    }

    private function lastCheckLabel(mixed $value): string
    {
        $checkedAt = $this->checkedAt($value);

        return $checkedAt ? $checkedAt->format('H:i:s') : 'Unknown';
    }

    private function lastCheckDescription(mixed $value): string
    {
        $checkedAt = $this->checkedAt($value);

        return $checkedAt ? $checkedAt->diffForHumans(now()) : 'No timestamp available';
    }

    private function checkedAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
