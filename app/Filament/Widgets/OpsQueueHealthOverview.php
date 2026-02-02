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
        $driver = (string) config('queue.default', 'sync');
        $queue = (string) config("queue.connections.{$driver}.queue", 'default');

        $metric = QueueMetric::query()
            ->where('driver', $driver)
            ->where('queue', $queue)
            ->latest('captured_at')
            ->first();

        $depth = $metric?->depth ?? 0;
        $oldest = $metric?->oldest_age_seconds;
        $oldestLabel = $oldest !== null ? $this->formatDuration($oldest) : 'N/A';
        $failed = $metric?->failed_count ?? 0;
        $throughput = $metric?->throughput_per_min ?? 0;

        return [
            Stat::make('Queue depth', (string) $depth)
                ->description("{$driver}:{$queue}")
                ->color($depth > 0 ? 'warning' : 'success'),
            Stat::make('Oldest job', $oldestLabel)
                ->description('Age')
                ->color($oldest !== null && $oldest > 300 ? 'warning' : 'gray'),
            Stat::make('Failed jobs', (string) $failed)
                ->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Throughput', (string) $throughput)
                ->description('Jobs/min')
                ->color('gray'),
        ];
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
