<?php

namespace App\Jobs;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Contracts\CacheWriteBackService;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use App\Services\VerificationOutputMapper;
use App\Services\VerificationResultsMerger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinalizeVerificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $jobId)
    {
    }

    public function handle(VerificationResultsMerger $merger, JobStorage $storage, CacheWriteBackService $writeBack): void
    {
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
            $this->markJobFailed($job, 'One or more chunks failed.');

            return;
        }

        if ($job->chunks->isNotEmpty() && $job->chunks->contains(fn ($chunk) => $chunk->status !== 'completed')) {
            return;
        }

        try {
            $outputDisk = $job->output_disk ?: ($job->input_disk ?: $storage->disk());
            $result = $merger->merge($job, $job->chunks, $outputDisk);

            if (! empty($result['missing'])) {
                $this->markJobFailed($job, 'Chunk outputs missing during finalization.', [
                    'missing' => $result['missing'],
                ]);

                return;
            }

            $writeBack->writeBack($job, $result);

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
        } catch (Throwable $exception) {
            $this->markJobFailed($job, 'Finalization failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markJobFailed(VerificationJob $job, string $message, array $context = []): void
    {
        $job->update([
            'status' => VerificationJobStatus::Failed,
            'finished_at' => now(),
            'error_message' => $message,
            'failure_source' => VerificationJob::FAILURE_SOURCE_ENGINE,
        ]);

        $job->addLog('finalize_failed', $message, $context);
    }

    /**
     * @param array{disk: string, keys: array<string, string>, counts: array<string, int>} $result
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
