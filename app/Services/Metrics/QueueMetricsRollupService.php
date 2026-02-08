<?php

namespace App\Services\Metrics;

use App\Models\QueueMetric;
use App\Models\QueueMetricsRollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class QueueMetricsRollupService
{
    public function rollup(int $hourWindow = 48, int $dayWindow = 30): int
    {
        $total = 0;

        $total += $this->rollupByPeriod(
            'hour',
            now()->subHours(max(1, $hourWindow))->startOfHour()
        );

        $total += $this->rollupByPeriod(
            'day',
            now()->subDays(max(1, $dayWindow))->startOfDay()
        );

        return $total;
    }

    private function rollupByPeriod(string $periodType, Carbon $from): int
    {
        $metrics = QueueMetric::query()
            ->where('captured_at', '>=', $from)
            ->orderBy('captured_at')
            ->get();

        if ($metrics->isEmpty()) {
            return 0;
        }

        $grouped = $metrics->groupBy(function (QueueMetric $metric) use ($periodType): string {
            $periodStart = $periodType === 'hour'
                ? $metric->captured_at->copy()->startOfHour()
                : $metric->captured_at->copy()->startOfDay();

            return implode('|', [
                $metric->driver,
                $metric->queue,
                $periodType,
                $periodStart->format('Y-m-d H:i:s'),
            ]);
        });

        $upserts = [];

        /** @var Collection<string, Collection<int, QueueMetric>> $grouped */
        foreach ($grouped as $key => $samples) {
            [$driver, $queue, $bucketType, $periodStart] = explode('|', $key, 4);

            $upserts[] = [
                'driver' => $driver,
                'queue' => $queue,
                'period_type' => $bucketType,
                'period_start' => Carbon::parse($periodStart),
                'samples' => $samples->count(),
                'avg_depth' => round((float) $samples->avg('depth'), 2),
                'max_depth' => (int) $samples->max('depth'),
                'avg_oldest_age_seconds' => $this->nullableAverage($samples, 'oldest_age_seconds'),
                'max_oldest_age_seconds' => $this->nullableMax($samples, 'oldest_age_seconds'),
                'avg_failed_count' => round((float) $samples->avg('failed_count'), 2),
                'max_failed_count' => (int) $samples->max('failed_count'),
                'avg_throughput_per_min' => $this->nullableAverage($samples, 'throughput_per_min'),
                'max_throughput_per_min' => $this->nullableMax($samples, 'throughput_per_min'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($upserts === []) {
            return 0;
        }

        QueueMetricsRollup::query()->upsert(
            $upserts,
            ['driver', 'queue', 'period_type', 'period_start'],
            [
                'samples',
                'avg_depth',
                'max_depth',
                'avg_oldest_age_seconds',
                'max_oldest_age_seconds',
                'avg_failed_count',
                'max_failed_count',
                'avg_throughput_per_min',
                'max_throughput_per_min',
                'updated_at',
            ]
        );

        return count($upserts);
    }

    /**
     * @param  Collection<int, QueueMetric>  $samples
     */
    private function nullableAverage(Collection $samples, string $field): ?float
    {
        $filtered = $samples->pluck($field)->filter(fn ($value): bool => $value !== null);

        if ($filtered->isEmpty()) {
            return null;
        }

        return round((float) $filtered->avg(), 2);
    }

    /**
     * @param  Collection<int, QueueMetric>  $samples
     */
    private function nullableMax(Collection $samples, string $field): ?int
    {
        $filtered = $samples->pluck($field)->filter(fn ($value): bool => $value !== null);

        if ($filtered->isEmpty()) {
            return null;
        }

        return (int) $filtered->max();
    }
}
