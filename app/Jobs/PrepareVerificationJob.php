<?php

namespace App\Jobs;

use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PrepareVerificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(public string $jobId)
    {
        $this->connection = 'redis_prepare';
        $this->queue = (string) config('queue.connections.redis_prepare.queue', 'prepare');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'lane:prepare',
            'verification_job:'.$this->jobId,
        ];
    }

    public function handle(): void
    {
        $job = VerificationJob::query()->find($this->jobId);

        if (! $job || $job->status !== VerificationJobStatus::Pending) {
            return;
        }

        $job->addLog('prepare_started', 'Preparing job for verification pipeline.');

        if ($job->chunks()->exists()) {
            $this->purgeExistingChunks($job);
        }

        $job->status = VerificationJobStatus::Processing;
        $job->started_at = $job->started_at ?: now();
        $job->prepared_at = null;
        $job->save();

        ParseAndChunkJob::dispatch($job->id);
    }

    private function purgeExistingChunks(VerificationJob $job): void
    {
        $job->load('chunks');

        foreach ($job->chunks as $chunk) {
            if ($chunk->input_disk && $chunk->input_key) {
                Storage::disk($chunk->input_disk)->delete($chunk->input_key);
            }
        }

        $job->chunks()->delete();
    }
}
