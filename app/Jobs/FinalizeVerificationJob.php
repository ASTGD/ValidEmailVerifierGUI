<?php

namespace App\Jobs;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobMetricsRecorder;
use App\Services\JobStorage;
use App\Services\VerificationOutputMapper;
use App\Services\VerificationResultsMerger;
use App\Support\EngineSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinalizeVerificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(public string $jobId)
    {
        $this->connection = 'redis_finalize';
        $this->queue = (string) config('queue.connections.redis_finalize.queue', 'finalize');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("finalize:{$this->jobId}"))
                ->expireAfter($this->timeout + 120)
                ->releaseAfter(30),
        ];
    }

    public function handle(
        VerificationResultsMerger $merger,
        JobStorage $storage,
        JobMetricsRecorder $metricsRecorder
    ): void {
        $job = VerificationJob::query()
            ->with('chunks')
            ->find($this->jobId);

        if (! $job) {
            return;
        }

        $hasCached = $job->cached_valid_key || $job->cached_invalid_key || $job->cached_risky_key;

        if ($job->chunks->isEmpty() && ! $hasCached) {
            return;
        }

        $hasFinalKeys = $job->valid_key && $job->invalid_key && $job->risky_key;

        if ($job->status === VerificationJobStatus::Completed && $hasFinalKeys) {
            return;
        }

        if ($job->status === VerificationJobStatus::Failed) {
            return;
        }

        if ($job->chunks->isNotEmpty() && $job->chunks->contains(fn ($chunk) => $chunk->status === 'failed')) {
            $this->markJobFailed($job, 'One or more chunks failed.', [], $metricsRecorder);

            return;
        }

        if ($job->chunks->isNotEmpty() && $job->chunks->contains(fn ($chunk) => $chunk->status !== 'completed')) {
            return;
        }

        try {
            $metricPayload = [
                'progress_percent' => 75,
                'total_emails' => $job->total_emails,
                'cache_hit_count' => $job->cached_count,
            ];

            if ($job->total_emails !== null) {
                $metricPayload['cache_miss_count'] = max(0, (int) $job->total_emails - (int) $job->cached_count);
            }

            $metricsRecorder->recordPhase($job, 'finalize', $metricPayload);

            $outputDisk = $job->output_disk ?: ($job->input_disk ?: $storage->disk());
            $result = $merger->merge($job, $job->chunks, $outputDisk);

            if (! empty($result['missing'])) {
                $this->markJobFailed($job, 'Chunk outputs missing during finalization.', [
                    'missing' => $result['missing'],
                ], $metricsRecorder);

                return;
            }

            $counts = $result['counts'];
            $validCount = (int) ($counts['valid'] ?? 0);
            $invalidCount = (int) ($counts['invalid'] ?? 0);
            $riskyCount = (int) ($counts['risky'] ?? 0);
            $totalFromCounts = $validCount + $invalidCount + $riskyCount;
            $singleResult = null;

            if ($job->origin === VerificationJobOrigin::SingleCheck) {
                $singleResult = $this->extractSingleResult($result);
            }

            $updatePayload = [
                'status' => VerificationJobStatus::Completed,
                'output_disk' => $result['disk'],
                'valid_key' => $result['keys']['valid'] ?? null,
                'invalid_key' => $result['keys']['invalid'] ?? null,
                'risky_key' => $result['keys']['risky'] ?? null,
                'output_key' => $job->output_key ?: ($result['keys']['valid'] ?? null),
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'risky_count' => $riskyCount,
                'total_emails' => $job->total_emails ?: $totalFromCounts,
                'finished_at' => now(),
                'error_message' => null,
                'failure_source' => null,
                'failure_code' => null,
            ];

            if ($singleResult) {
                $updatePayload['single_result_status'] = $singleResult['status'] ?? null;
                $updatePayload['single_result_sub_status'] = $singleResult['sub_status'] ?? null;
                $updatePayload['single_result_score'] = $singleResult['score'] ?? null;
                $updatePayload['single_result_reason'] = $singleResult['reason'] ?? null;
                $updatePayload['single_result_verified_at'] = now();
            }

            $job->update($updatePayload);

            $job->addLog('finalized', 'Final job outputs merged.', [
                'output_disk' => $job->output_disk,
                'valid_key' => $job->valid_key,
                'invalid_key' => $job->invalid_key,
                'risky_key' => $job->risky_key,
                'valid_count' => $job->valid_count,
                'invalid_count' => $job->invalid_count,
                'risky_count' => $job->risky_count,
            ]);

            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'total_emails' => $job->total_emails,
                'processed_emails' => $job->total_emails,
                'writeback_status' => null,
            ]);

            $this->scheduleWriteBack($job, $metricsRecorder);
        } catch (Throwable $exception) {
            $this->markJobFailed($job, 'Finalization failed.', [
                'error' => $exception->getMessage(),
            ], $metricsRecorder);
        }
    }

    private function scheduleWriteBack(VerificationJob $job, JobMetricsRecorder $metricsRecorder): void
    {
        if (! EngineSettings::cacheWritebackEnabled()) {
            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => 'disabled',
                'writeback_attempted_count' => 0,
                'writeback_written_count' => 0,
                'writeback_last_error' => null,
                'writeback_queued_at' => null,
                'writeback_started_at' => null,
                'writeback_finished_at' => now(),
            ]);

            return;
        }

        if (! $job->cache_miss_key) {
            $metricsRecorder->recordPhase($job, 'completed', [
                'progress_percent' => 100,
                'writeback_status' => 'skipped',
                'writeback_attempted_count' => 0,
                'writeback_written_count' => 0,
                'writeback_last_error' => null,
                'writeback_queued_at' => null,
                'writeback_started_at' => null,
                'writeback_finished_at' => now(),
            ]);

            return;
        }

        $queuedAt = now();
        $metricsRecorder->recordPhase($job, 'completed', [
            'progress_percent' => 100,
            'writeback_status' => 'queued',
            'writeback_attempted_count' => 0,
            'writeback_written_count' => 0,
            'writeback_last_error' => null,
            'writeback_queued_at' => $queuedAt,
            'writeback_started_at' => null,
            'writeback_finished_at' => null,
        ]);

        DB::afterCommit(function () use ($job): void {
            WriteBackVerificationCacheJob::dispatch($job->id);
        });
    }

    private function markJobFailed(
        VerificationJob $job,
        string $message,
        array $context = [],
        ?JobMetricsRecorder $metricsRecorder = null
    ): void {
        $job->update([
            'status' => VerificationJobStatus::Failed,
            'finished_at' => now(),
            'error_message' => $message,
            'failure_source' => VerificationJob::FAILURE_SOURCE_ENGINE,
        ]);

        $job->addLog('finalize_failed', $message, $context);

        if ($metricsRecorder) {
            $metricsRecorder->recordPhase($job, 'failed', [
                'progress_percent' => 100,
            ]);
        }
    }

    /**
     * @param  array{disk: string, keys: array<string, string>, counts: array<string, int>}  $result
     * @return array{email: string, status: string, sub_status: string, score: int|null, reason: string}|null
     */
    private function extractSingleResult(array $result): ?array
    {
        $disk = $result['disk'] ?? null;
        $keys = $result['keys'] ?? [];

        if (! $disk) {
            return null;
        }

        foreach (['valid', 'invalid', 'risky'] as $type) {
            $key = $keys[$type] ?? null;

            if (! $key) {
                continue;
            }

            $row = $this->readFirstResultRow($disk, $key, $type);

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{email: string, status: string, sub_status: string, score: int|null, reason: string}|null
     */
    private function readFirstResultRow(string $disk, string $key, string $sourceStatus): ?array
    {
        $stream = Storage::disk($disk)->readStream($key);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            while (($line = fgets($stream)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line === '' || ! str_contains($line, '@')) {
                    continue;
                }

                $columns = str_getcsv($line);

                if ($columns === []) {
                    continue;
                }

                $email = trim((string) ($columns[0] ?? ''));
                $status = trim((string) ($columns[1] ?? ''));

                if ($email === '') {
                    continue;
                }

                if (count($columns) >= 5 && in_array(strtolower($status), ['valid', 'invalid', 'risky'], true)) {
                    $subStatus = trim((string) ($columns[2] ?? ''));
                    $scoreRaw = $columns[3] ?? null;
                    $reason = trim((string) ($columns[4] ?? ''));

                    return [
                        'email' => $email,
                        'status' => $status,
                        'sub_status' => $subStatus,
                        'score' => is_numeric($scoreRaw) ? (int) $scoreRaw : null,
                        'reason' => $reason,
                    ];
                }

                $reason = trim((string) ($columns[1] ?? ''));
                /** @var VerificationOutputMapper $mapper */
                $mapper = app(VerificationOutputMapper::class);
                $mapped = $mapper->map($email, $sourceStatus, $reason, null);

                return [
                    'email' => $mapped['email'],
                    'status' => $mapped['status'],
                    'sub_status' => $mapped['sub_status'],
                    'score' => $mapped['score'],
                    'reason' => $mapped['reason'],
                ];
            }
        } finally {
            fclose($stream);
        }

        return null;
    }
}
