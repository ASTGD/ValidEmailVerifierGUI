<?php

namespace App\Filament\Widgets;

use App\Models\QueueMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsQueueHealthOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $laneMetrics = $this->latestLaneMetrics();
        $depth = array_sum(array_map(fn (QueueMetric $metric): int => (int) ($metric->depth ?? 0), $laneMetrics));
        $oldestMetric = collect($laneMetrics)->sortByDesc(fn (QueueMetric $metric): int => (int) ($metric->oldest_age_seconds ?? -1))->first();
        $oldestLabel = $oldestMetric?->oldest_age_seconds !== null
            ? $this->formatDuration((int) $oldestMetric->oldest_age_seconds)
            : 'N/A';
        $busiestMetric = collect($laneMetrics)->sortByDesc(fn (QueueMetric $metric): int => (int) ($metric->depth ?? 0))->first();
        $failed = (int) collect($laneMetrics)->max(fn (QueueMetric $metric): int => (int) ($metric->failed_count ?? 0));
        $throughput = (int) collect($laneMetrics)->max(fn (QueueMetric $metric): int => (int) ($metric->throughput_per_min ?? 0));
        $depthSummary = collect($laneMetrics)
            ->map(fn (QueueMetric $metric): string => sprintf('%s:%d', $metric->queue, (int) ($metric->depth ?? 0)))
            ->implode(' | ');

        return [
            Stat::make('Queue depth', (string) $depth)
                ->description($depthSummary !== '' ? $depthSummary : 'No queue metrics yet')
                ->color($depth > 0 ? 'warning' : 'success'),
            Stat::make(
                'Busiest queue',
                $busiestMetric ? sprintf('%s (%d)', strtoupper($busiestMetric->queue), (int) ($busiestMetric->depth ?? 0)) : 'N/A'
            )
                ->description($busiestMetric ? $busiestMetric->driver : 'No queue metrics yet')
                ->color($busiestMetric && (int) ($busiestMetric->depth ?? 0) > 0 ? 'warning' : 'gray'),
            Stat::make('Oldest job', $oldestLabel)
                ->description($oldestMetric ? 'Queue: '.$oldestMetric->queue : 'Age')
                ->color(
                    $oldestMetric && $oldestMetric->oldest_age_seconds !== null && (int) $oldestMetric->oldest_age_seconds > 300
                        ? 'warning'
                        : 'gray'
                ),
            Stat::make('Failed jobs', (string) $failed)
                ->description('Throughput: '.$throughput.'/min')
                ->color($failed > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * @return array<int, QueueMetric>
     */
    private function latestLaneMetrics(): array
    {
        $metrics = [];

        foreach ($this->trackedLanes() as $lane) {
            $metric = QueueMetric::query()
                ->where('driver', $lane['driver'])
                ->where('queue', $lane['queue'])
                ->latest('captured_at')
                ->first();

            if (! $metric) {
                continue;
            }

            $metrics[] = $metric;
        }

        return $metrics;
    }

    /**
     * @return array<int, array{driver: string, queue: string}>
     */
    private function trackedLanes(): array
    {
        $defaultConnection = (string) config('queue.default', 'sync');
        $lanes = [];

        if ($defaultConnection === 'redis') {
            $lanes[] = [
                'driver' => 'redis',
                'queue' => (string) config('queue.connections.redis.queue', 'default'),
            ];

            foreach (['redis_prepare', 'redis_parse', 'redis_finalize', 'redis_import', 'redis_cache_writeback'] as $connection) {
                $queue = (string) config("queue.connections.{$connection}.queue", '');

                if ($queue === '') {
                    continue;
                }

                $lanes[] = [
                    'driver' => $connection,
                    'queue' => $queue,
                ];
            }

            return $this->dedupeLanes($lanes);
        }

        $defaultQueue = (string) config("queue.connections.{$defaultConnection}.queue", 'default');

        return [[
            'driver' => $defaultConnection,
            'queue' => $defaultQueue,
        ]];
    }

    /**
     * @param  array<int, array{driver: string, queue: string}>  $lanes
     * @return array<int, array{driver: string, queue: string}>
     */
    private function dedupeLanes(array $lanes): array
    {
        $deduped = [];
        $seen = [];

        foreach ($lanes as $lane) {
            $key = $lane['driver'].'|'.$lane['queue'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $lane;
        }

        return $deduped;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = floor($seconds / 60);
        $remainder = $seconds % 60;

        return sprintf('%dm %ds', $minutes, $remainder);
    }
}
