<?php

namespace App\Services\Metrics;

use App\Enums\VerificationJobStatus;
use App\Models\QueueMetric;
use App\Models\VerificationJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueMetricsService
{
    /**
     * Capture queue metrics for all active queue lanes.
     */
    public function capture(): int
    {
        $targets = $this->queueTargets();
        if ($targets === []) {
            return 0;
        }

        $interval = max(1, (int) config('engine.metrics_sample_interval_seconds', 60));
        $failedCount = (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $throughput = VerificationJob::query()
            ->where('status', VerificationJobStatus::Completed)
            ->where('finished_at', '>=', now()->subMinute())
            ->count();

        $captured = 0;

        foreach ($targets as $target) {
            if (! $this->shouldCapture($target['connection'], $target['queue'], $interval)) {
                continue;
            }

            try {
                $metrics = match ($target['driver']) {
                    'database' => $this->captureDatabaseQueue($target['connection'], $target['queue']),
                    'redis' => $this->captureRedisQueue($target['connection'], $target['queue']),
                    default => [
                        'depth' => 0,
                        'oldest_age_seconds' => null,
                    ],
                };
            } catch (\Throwable $exception) {
                continue;
            }

            QueueMetric::create([
                'driver' => $target['connection'],
                'queue' => $target['queue'],
                'depth' => (int) ($metrics['depth'] ?? 0),
                'oldest_age_seconds' => $metrics['oldest_age_seconds'] ?? null,
                'failed_count' => $failedCount,
                'throughput_per_min' => $throughput,
                'captured_at' => now(),
            ]);

            $captured++;
        }

        return $captured;
    }

    private function shouldCapture(string $connection, string $queue, int $interval): bool
    {
        $last = QueueMetric::query()
            ->where('driver', $connection)
            ->where('queue', $queue)
            ->latest('captured_at')
            ->first();

        if (! $last || ! $last->captured_at) {
            return true;
        }

        return $last->captured_at->diffInSeconds(now()) >= $interval;
    }

    /**
     * @return array<int, array{connection: string, driver: string, queue: string}>
     */
    private function queueTargets(): array
    {
        $defaultConnection = (string) config('queue.default', 'sync');
        $targets = [];

        if ($defaultConnection === 'redis') {
            foreach (['redis_prepare', 'redis_parse', 'redis_finalize', 'redis_import', 'redis_cache_writeback'] as $connection) {
                $driver = (string) config("queue.connections.{$connection}.driver", '');
                $queue = (string) config("queue.connections.{$connection}.queue", '');

                if ($driver !== 'redis' || $queue === '') {
                    continue;
                }

                $targets[] = [
                    'connection' => $connection,
                    'driver' => 'redis',
                    'queue' => $queue,
                ];
            }

            $defaultQueue = (string) config('queue.connections.redis.queue', '');
            if ($defaultQueue !== '') {
                $targets[] = [
                    'connection' => 'redis',
                    'driver' => 'redis',
                    'queue' => $defaultQueue,
                ];
            }

            return $this->dedupeTargets($targets);
        }

        $driver = (string) config("queue.connections.{$defaultConnection}.driver", '');
        if (! in_array($driver, ['database', 'redis'], true)) {
            return [];
        }

        return [[
            'connection' => $defaultConnection,
            'driver' => $driver,
            'queue' => (string) config("queue.connections.{$defaultConnection}.queue", 'default'),
        ]];
    }

    /**
     * @param  array<int, array{connection: string, driver: string, queue: string}>  $targets
     * @return array<int, array{connection: string, driver: string, queue: string}>
     */
    private function dedupeTargets(array $targets): array
    {
        $seen = [];
        $deduped = [];

        foreach ($targets as $target) {
            $key = $target['connection'].'|'.$target['queue'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $target;
        }

        return $deduped;
    }

    /**
     * @return array{depth: int, oldest_age_seconds: int|null}
     */
    private function captureDatabaseQueue(string $connection, string $queue): array
    {
        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $dbConnection = config("queue.connections.{$connection}.connection");

        $depthQuery = $dbConnection
            ? DB::connection((string) $dbConnection)->table($table)
            : DB::table($table);
        $oldestQuery = $dbConnection
            ? DB::connection((string) $dbConnection)->table($table)
            : DB::table($table);

        $depth = (int) $depthQuery->where('queue', $queue)->count();
        $oldest = $oldestQuery->where('queue', $queue)->min('created_at');

        return [
            'depth' => $depth,
            'oldest_age_seconds' => $oldest ? now()->diffInSeconds($oldest) : null,
        ];
    }

    /**
     * @return array{depth: int, oldest_age_seconds: int|null}
     */
    private function captureRedisQueue(string $connection, string $queue): array
    {
        $redisConnection = (string) config("queue.connections.{$connection}.connection", 'default');
        $redis = Redis::connection($redisConnection);

        $prefix = (string) config("queue.connections.{$connection}.prefix", 'queues');
        $prefix = rtrim($prefix, ':');
        $key = $prefix.':'.$queue;
        $depth = (int) $redis->llen($key);
        $depth += (int) $redis->zcard($key.':delayed');
        $depth += (int) $redis->zcard($key.':reserved');

        $oldestPayload = $redis->lindex($key, 0);
        $oldestAge = $this->parsePayloadAge($oldestPayload);

        return [
            'depth' => $depth,
            'oldest_age_seconds' => $oldestAge,
        ];
    }

    private function parsePayloadAge(mixed $payload): ?int
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        $pushedAt = $decoded['pushedAt'] ?? $decoded['pushed_at'] ?? $decoded['created_at'] ?? null;

        if (! $pushedAt) {
            return null;
        }

        try {
            if (is_numeric($pushedAt)) {
                return now()->diffInSeconds(Carbon::createFromTimestamp((int) $pushedAt));
            }

            return now()->diffInSeconds(Carbon::parse($pushedAt));
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
