<?php

namespace App\Jobs;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use App\Services\VerificationResultsMerger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(VerificationResultsMerger $merger, JobStorage $storage): void
    {
        $job = VerificationJob::query()
            ->with('chunks')
            ->find($this->jobId);

        if (! $job) {
            return;
        }

        if ($job->chunks->isEmpty()) {
            return;
        }

        $hasFinalKeys = $job->valid_key && $job->invalid_key && $job->risky_key;

        if ($job->status === VerificationJobStatus::Completed && $hasFinalKeys) {
            return;
        }

        if ($job->status === VerificationJobStatus::Failed) {
            return;
        }

        if ($job->chunks->contains(fn ($chunk) => $chunk->status === 'failed')) {
            $this->markJobFailed($job, 'One or more chunks failed.');

            return;
        }

        if ($job->chunks->contains(fn ($chunk) => $chunk->status !== 'completed')) {
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

            $counts = $result['counts'];
            $validCount = (int) ($counts['valid'] ?? 0);
            $invalidCount = (int) ($counts['invalid'] ?? 0);
            $riskyCount = (int) ($counts['risky'] ?? 0);
            $totalFromCounts = $validCount + $invalidCount + $riskyCount;

            $job->update([
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
            ]);

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
}
