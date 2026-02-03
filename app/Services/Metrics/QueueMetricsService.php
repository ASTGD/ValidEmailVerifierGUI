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
    public function capture(): ?QueueMetric
    {
        $driver = (string) config('queue.default', 'sync');
        $queue = (string) config("queue.connections.{$driver}.queue", 'default');
        $interval = max(1, (int) config('engine.metrics_sample_interval_seconds', 60));

        $last = QueueMetric::query()
            ->where('driver', $driver)
            ->where('queue', $queue)
            ->latest('captured_at')
            ->first();

        if ($last && $last->captured_at && $last->captured_at->diffInSeconds(now()) < $interval) {
            return null;
        }

        try {
            $metrics = match ($driver) {
                'database' => $this->captureDatabaseQueue($queue),
                'redis' => $this->captureRedisQueue($queue),
                default => [
                    'depth' => 0,
                    'oldest_age_seconds' => null,
                ],
            };
        } catch (\Throwable $exception) {
            return null;
        }

        $failedCount = (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $throughput = VerificationJob::query()
            ->where('status', VerificationJobStatus::Completed)
            ->where('finished_at', '>=', now()->subMinute())
            ->count();

        return QueueMetric::create([
            'driver' => $driver,
            'queue' => $queue,
            'depth' => (int) ($metrics['depth'] ?? 0),
            'oldest_age_seconds' => $metrics['oldest_age_seconds'] ?? null,
            'failed_count' => $failedCount,
            'throughput_per_min' => $throughput,
            'captured_at' => now(),
        ]);
    }

    /**
     * @return array{depth: int, oldest_age_seconds: int|null}
     */
    private function captureDatabaseQueue(string $queue): array
    {
        $table = config('queue.connections.database.table', 'jobs');
        $depth = (int) DB::table($table)->where('queue', $queue)->count();
        $oldest = DB::table($table)->where('queue', $queue)->min('created_at');

        return [
            'depth' => $depth,
            'oldest_age_seconds' => $oldest ? now()->diffInSeconds($oldest) : null,
        ];
    }

    /**
     * @return array{depth: int, oldest_age_seconds: int|null}
     */
    private function captureRedisQueue(string $queue): array
    {
        $connection = (string) config('queue.connections.redis.connection', 'default');
        $redis = Redis::connection($connection);

        $prefix = (string) config('queue.connections.redis.prefix', 'queues');
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

    private function parsePayloadAge(?string $payload): ?int
    {
        if (! $payload) {
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
