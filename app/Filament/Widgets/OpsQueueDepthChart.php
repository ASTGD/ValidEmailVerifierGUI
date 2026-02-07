<?php

namespace App\Filament\Widgets;

use App\Models\QueueMetric;
use Filament\Widgets\ChartWidget;

class OpsQueueDepthChart extends ChartWidget
{
    protected ?string $heading = 'Queue Depth';

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $lanes = $this->trackedLanes();
        if ($lanes === []) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $series = [];
        $baseMetrics = collect();
        $baseLength = 0;

        foreach ($lanes as $lane) {
            $metrics = QueueMetric::query()
                ->where('driver', $lane['driver'])
                ->where('queue', $lane['queue'])
                ->latest('captured_at')
                ->limit(30)
                ->get()
                ->reverse()
                ->values();

            $key = $lane['driver'].'|'.$lane['queue'];
            $series[$key] = $metrics;

            if ($metrics->count() > $baseLength) {
                $baseLength = $metrics->count();
                $baseMetrics = $metrics;
            }
        }

        if ($baseMetrics->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $baseTimestamps = $baseMetrics->map(fn (QueueMetric $metric): int => $metric->captured_at?->timestamp ?? 0)->values();
        $labels = $baseMetrics->map(fn (QueueMetric $metric): string => $metric->captured_at?->format('H:i') ?? '-')
            ->values()
            ->toArray();

        $datasets = [];

        foreach ($lanes as $lane) {
            $key = $lane['driver'].'|'.$lane['queue'];
            $metrics = $series[$key] ?? collect();
            $depthByTimestamp = $metrics->mapWithKeys(
                fn (QueueMetric $metric): array => [($metric->captured_at?->timestamp ?? 0) => (int) ($metric->depth ?? 0)]
            );

            $datasets[] = [
                'label' => strtoupper($lane['queue']),
                'data' => $baseTimestamps->map(fn (int $timestamp): ?int => $depthByTimestamp->get($timestamp))->toArray(),
                'borderColor' => $this->laneColor($lane['queue']),
                'backgroundColor' => $this->laneColor($lane['queue']),
                'spanGaps' => true,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
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

            foreach (['redis_prepare', 'redis_parse', 'redis_finalize', 'redis_import'] as $connection) {
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

    private function laneColor(string $queue): string
    {
        return match (strtolower($queue)) {
            'prepare' => '#2563eb',
            'parse' => '#f59e0b',
            'finalize' => '#10b981',
            'imports' => '#ef4444',
            default => '#6b7280',
        };
    }

    protected function getType(): string
    {
        return 'line';
    }
}
