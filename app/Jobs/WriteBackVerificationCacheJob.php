<?php

namespace App\Jobs;

use App\Contracts\CacheWriteBackService;
use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobMetricsRecorder;
use App\Support\EngineSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WriteBackVerificationCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 2;

    public bool $failOnTimeout = true;

    public function __construct(public string $jobId)
    {
        $this->connection = 'redis_cache_writeback';
        $this->queue = (string) config('queue.connections.redis_cache_writeback.queue', 'cache_writeback');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [120, 300];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:cache_writeback',
            'verification_job:'.$this->jobId,
        ];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("cache-writeback:{$this->jobId}"))
                ->expireAfter($this->timeout + 120)
                ->releaseAfter(60),
        ];
    }

    public function handle(CacheWriteBackService $writeBack, JobMetricsRecorder $metricsRecorder): void
    {
        $job = VerificationJob::query()->find($this->jobId);

        if (! $job || $job->status !== VerificationJobStatus::Completed) {
            return;
        }

        if (! EngineSettings::cacheWritebackEnabled()) {
            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => 'disabled',
                'writeback_last_error' => null,
                'writeback_started_at' => null,
                'writeback_finished_at' => now(),
            ]);

            return;
        }

        if (! $job->cache_miss_key) {
            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => 'skipped',
                'writeback_last_error' => null,
                'writeback_started_at' => null,
                'writeback_finished_at' => now(),
            ]);

            return;
        }

        $metricsRecorder->recordPhase($job, 'writeback', [
            'progress_percent' => 90,
            'writeback_status' => 'running',
            'writeback_last_error' => null,
            'writeback_started_at' => now(),
            'writeback_finished_at' => null,
        ]);

        try {
            $result = $writeBack->writeBack($job, $this->writeBackPayload($job));

            $rawStatus = strtolower((string) ($result['status'] ?? 'completed'));
            $status = $this->normalizeStatus($rawStatus);
            $lastError = $status === 'failed'
                ? sprintf('Cache write-back returned status: %s', $rawStatus)
                : null;

            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => $status,
                'writeback_attempted_count' => (int) ($result['attempted'] ?? 0),
                'writeback_written_count' => (int) ($result['written'] ?? 0),
                'writeback_last_error' => $lastError,
                'writeback_finished_at' => now(),
            ]);

            $job->addLog('cache_writeback_completed', 'Cache write-back finished.', [
                'status' => $status,
                'service_status' => $rawStatus,
                'attempted' => (int) ($result['attempted'] ?? 0),
                'written' => (int) ($result['written'] ?? 0),
                'error' => $lastError,
            ]);
        } catch (Throwable $exception) {
            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => 'failed',
                'writeback_last_error' => $exception->getMessage(),
                'writeback_finished_at' => now(),
            ]);

            $job->addLog('cache_writeback_failed', 'Cache write-back failed.', [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{disk: string, keys: array<string, string>}
     */
    private function writeBackPayload(VerificationJob $job): array
    {
        $disk = (string) ($job->output_disk ?: ($job->input_disk ?: config('filesystems.default')));

        $keys = [
            'valid' => $job->valid_key,
            'invalid' => $job->invalid_key,
            'risky' => $job->risky_key,
        ];

        return [
            'disk' => $disk,
            'keys' => array_filter($keys, static fn (?string $key): bool => filled($key)),
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'completed',
            'disabled' => 'disabled',
            'no_cache_miss', 'cache_miss_missing', 'skipped' => 'skipped',
            default => 'failed',
        };
    }
}
