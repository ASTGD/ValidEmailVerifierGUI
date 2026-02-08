<?php

namespace App\Console\Commands;

use App\Services\QueueHealth\QueueHealthEvaluator;
use App\Services\QueueHealth\QueueHealthNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckQueueHealth extends Command
{
    protected $signature = 'ops:queue-health {--json : Output queue health report as JSON}';

    protected $description = 'Check queue reliability health and optionally emit alerts.';

    public function handle(QueueHealthEvaluator $evaluator, QueueHealthNotifier $notifier): int
    {
        $report = (bool) config('queue_health.enabled', true)
            ? $evaluator->evaluate()
            : $this->disabledReport();

        Cache::put(
            (string) config('queue_health.report_cache_key', 'queue_health:last_report'),
            $report,
            now()->addDay()
        );

        $notifier->notify($report);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHumanReport($report);
        }

        return ($report['status'] ?? 'healthy') === 'critical'
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  array{status: string, checked_at: string, summary: array{critical: int, warning: int}, issues: array<int, array{severity: string, title: string, detail: string}>}  $report
     */
    private function renderHumanReport(array $report): void
    {
        $status = strtoupper((string) ($report['status'] ?? 'unknown'));
        $critical = (int) data_get($report, 'summary.critical', 0);
        $warning = (int) data_get($report, 'summary.warning', 0);

        $this->line(sprintf('Queue health: %s (critical=%d, warning=%d)', $status, $critical, $warning));

        $issues = (array) ($report['issues'] ?? []);

        if ($issues === []) {
            $this->line('No active queue incidents.');

            return;
        }

        foreach ($issues as $issue) {
            $this->line(sprintf(
                '- [%s] %s: %s',
                strtoupper((string) ($issue['severity'] ?? 'warning')),
                (string) ($issue['title'] ?? 'Issue'),
                (string) ($issue['detail'] ?? '')
            ));
        }
    }

    /**
     * @return array{status: string, checked_at: string, issues: array<int, array<string, mixed>>, summary: array{critical: int, warning: int}, meta: array<string, mixed>}
     */
    private function disabledReport(): array
    {
        return [
            'status' => 'disabled',
            'checked_at' => now()->toIso8601String(),
            'issues' => [],
            'summary' => [
                'critical' => 0,
                'warning' => 0,
            ],
            'meta' => [
                'enabled' => false,
            ],
        ];
    }
}
