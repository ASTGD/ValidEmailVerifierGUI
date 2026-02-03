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
        $driver = (string) config('queue.default', 'sync');
        $queue = (string) config("queue.connections.{$driver}.queue", 'default');

        $metrics = QueueMetric::query()
            ->where('driver', $driver)
            ->where('queue', $queue)
            ->latest('captured_at')
            ->limit(30)
            ->get()
            ->reverse();

        $labels = $metrics->map(fn (QueueMetric $metric) => $metric->captured_at?->format('H:i') ?? '-')
            ->values()
            ->toArray();
        $depth = $metrics->map(fn (QueueMetric $metric) => $metric->depth ?? 0)
            ->values()
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Depth',
                    'data' => $depth,
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
