<?php

namespace App\Console\Commands;

use App\Models\QueueIncident;
use App\Models\QueueMetric;
use App\Models\QueueMetricsRollup;
use App\Services\QueueHealth\QueueHealthEvaluator;
use Illuminate\Console\Command;

class QueueSloReportCommand extends Command
{
    protected $signature = 'ops:queue-slo-report {--json : Output report as JSON}';

    protected $description = 'Generate queue SLO report with lane status, retry contracts, and incident summary.';

    public function handle(QueueHealthEvaluator $evaluator): int
    {
        $health = $evaluator->evaluate();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'health_status' => $health['status'] ?? 'unknown',
            'lanes' => $this->laneReport(),
            'retry_contracts' => $this->retryContractReport(),
            'open_incidents' => QueueIncident::query()->whereNull('resolved_at')->count(),
            'rollup_last_24h' => $this->rollupSummary(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHuman($report);
        }

        $critical = collect($report['lanes'])->contains(fn (array $lane): bool => $lane['status'] === 'critical')
            || collect($report['retry_contracts'])->contains(fn (array $contract): bool => ! $contract['safe'])
            || ($health['status'] ?? null) === 'critical';

        return $critical ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function laneReport(): array
    {
        $lanes = [];

        foreach ((array) config('queue_health.lanes', []) as $lane => $definition) {
            $driver = (string) ($definition['driver'] ?? '');
            $queue = (string) ($definition['queue'] ?? '');

            if ($driver === '' || $queue === '') {
                continue;
            }

            $metric = QueueMetric::query()
                ->where('driver', $driver)
                ->where('queue', $queue)
                ->latest('captured_at')
                ->first();

            $maxDepth = max(0, (int) ($definition['max_depth'] ?? 0));
            $maxAge = max(0, (int) ($definition['max_oldest_age_seconds'] ?? 0));
            $depth = (int) ($metric?->depth ?? 0);
            $age = $metric?->oldest_age_seconds !== null ? (int) $metric->oldest_age_seconds : null;

            $status = 'healthy';
            if ($maxAge > 0 && $age !== null && $age > $maxAge) {
                $status = 'critical';
            } elseif ($maxDepth > 0 && $depth > $maxDepth) {
                $status = 'warning';
            }

            $lanes[] = [
                'lane' => $lane,
                'driver' => $driver,
                'queue' => $queue,
                'depth' => $depth,
                'oldest_age_seconds' => $age,
                'threshold_depth' => $maxDepth,
                'threshold_oldest_age_seconds' => $maxAge,
                'status' => $status,
                'captured_at' => $metric?->captured_at?->toIso8601String(),
            ];
        }

        return $lanes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function retryContractReport(): array
    {
        $contracts = [];
        $buffer = max(0, (int) config('queue_slo.retry_safety_buffer_seconds', 30));

        foreach ((array) config('queue_slo.retry_contracts', []) as $connection => $contract) {
            $timeout = max(0, (int) ($contract['timeout'] ?? 0));
            $retryAfter = max(0, (int) config("queue.connections.{$connection}.retry_after", (int) ($contract['retry_after'] ?? 0)));
            $minimum = $timeout + $buffer;

            $contracts[] = [
                'connection' => (string) $connection,
                'timeout' => $timeout,
                'retry_after' => $retryAfter,
                'required_minimum' => $minimum + 1,
                'safe' => $timeout > 0 && $retryAfter > $minimum,
            ];
        }

        return $contracts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rollupSummary(): array
    {
        return QueueMetricsRollup::query()
            ->where('period_type', 'hour')
            ->where('period_start', '>=', now()->subDay()->startOfHour())
            ->orderBy('driver')
            ->orderBy('queue')
            ->get()
            ->map(fn (QueueMetricsRollup $rollup): array => [
                'driver' => $rollup->driver,
                'queue' => $rollup->queue,
                'period_start' => $rollup->period_start?->toIso8601String(),
                'samples' => (int) $rollup->samples,
                'avg_depth' => (float) $rollup->avg_depth,
                'max_depth' => (int) $rollup->max_depth,
                'avg_oldest_age_seconds' => $rollup->avg_oldest_age_seconds !== null ? (float) $rollup->avg_oldest_age_seconds : null,
                'max_oldest_age_seconds' => $rollup->max_oldest_age_seconds !== null ? (int) $rollup->max_oldest_age_seconds : null,
            ])
            ->all();
    }

    /**
     * @param  array{
     *     generated_at: string,
     *     health_status: string,
     *     lanes: array<int, array<string, mixed>>,
     *     retry_contracts: array<int, array<string, mixed>>,
     *     open_incidents: int
     * }  $report
     */
    private function renderHuman(array $report): void
    {
        $this->line(sprintf('Queue SLO Report (%s)', $report['generated_at']));
        $this->line('Health status: '.strtoupper((string) $report['health_status']));
        $this->line('Open incidents: '.(int) $report['open_incidents']);
        $this->line('');
        $this->line('Lane status:');

        foreach ($report['lanes'] as $lane) {
            $this->line(sprintf(
                '- %s (%s:%s) depth=%d/%d oldest=%s/%ds status=%s',
                $lane['lane'],
                $lane['driver'],
                $lane['queue'],
                (int) $lane['depth'],
                (int) $lane['threshold_depth'],
                $lane['oldest_age_seconds'] === null ? 'n/a' : (int) $lane['oldest_age_seconds'].'s',
                (int) $lane['threshold_oldest_age_seconds'],
                strtoupper((string) $lane['status'])
            ));
        }

        $this->line('');
        $this->line('Retry contracts:');

        foreach ($report['retry_contracts'] as $contract) {
            $this->line(sprintf(
                '- %s timeout=%ds retry_after=%ds required>%ds => %s',
                $contract['connection'],
                (int) $contract['timeout'],
                (int) $contract['retry_after'],
                (int) $contract['required_minimum'],
                $contract['safe'] ? 'SAFE' : 'UNSAFE'
            ));
        }
    }
}
