<?php

namespace App\Filament\Widgets;

use App\Models\SystemMetric;
use App\Support\EngineSettings;
use Filament\Widgets\ChartWidget;

class OpsSystemTrendChart extends ChartWidget
{
    protected ?string $heading = 'System Trends';

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $source = EngineSettings::metricsSource();
        $metrics = SystemMetric::query()
            ->where('source', $source)
            ->latest('captured_at')
            ->limit(30)
            ->get()
            ->reverse();

        $labels = $metrics->map(fn (SystemMetric $metric) => $metric->captured_at?->format('H:i') ?? '-')
            ->values()
            ->toArray();
        $cpu = $metrics->map(fn (SystemMetric $metric) => $metric->cpu_percent)
            ->values()
            ->toArray();
        $ram = $metrics->map(function (SystemMetric $metric) {
            if (! $metric->mem_total_mb || ! $metric->mem_used_mb) {
                return null;
            }

            return round(($metric->mem_used_mb / $metric->mem_total_mb) * 100, 1);
        })
            ->values()
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'CPU %',
                    'data' => $cpu,
                    'borderColor' => '#f59e0b',
                ],
                [
                    'label' => 'RAM %',
                    'data' => $ram,
                    'borderColor' => '#3b82f6',
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
