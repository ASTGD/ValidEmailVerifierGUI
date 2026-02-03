<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Support\EngineSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TempfailRetryPlanner
{
    public function __construct(private JobStorage $storage)
    {
    }

    /**
     * @return array{retry_count: int, tempfail_count: int, retry_chunk_id: string|null}
     */
    public function plan(VerificationJob $job, VerificationJobChunk $chunk, string $outputDisk): array
    {
        if ($outputDisk === '') {
            $outputDisk = $this->storage->disk();
        }

        if (! EngineSettings::tempfailRetryEnabled()) {
            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        $maxAttempts = EngineSettings::tempfailRetryMaxAttempts();
        if ($chunk->retry_attempt >= $maxAttempts) {
            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        if (! $chunk->risky_key) {
            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        $retryReasons = EngineSettings::tempfailRetryReasons();
        if ($retryReasons === []) {
            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        $stream = Storage::disk($outputDisk)->readStream($chunk->risky_key);
        if (! is_resource($stream)) {
            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        $filteredStream = tmpfile();
        $retryStream = tmpfile();
        $retryCount = 0;
        $filteredCount = 0;

        try {
            while (($line = fgets($stream)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line === '') {
                    continue;
                }

                [$email, $reason] = $this->parseLine($line);
                if ($email === '' || ! str_contains($email, '@')) {
                    continue;
                }

                if ($this->isRetryReason($reason, $retryReasons)) {
                    fwrite($retryStream, $email."\n");
                    $retryCount++;
                    continue;
                }

                fwrite($filteredStream, $line."\n");
                $filteredCount++;
            }
        } finally {
            fclose($stream);
        }

        if ($retryCount === 0) {
            fclose($filteredStream);
            fclose($retryStream);

            return ['retry_count' => 0, 'tempfail_count' => 0, 'retry_chunk_id' => null];
        }

        $filteredKey = $this->filteredRiskyKey($chunk->risky_key);
        $this->writeStream($outputDisk, $filteredKey, $filteredStream);
        fclose($filteredStream);

        $retryChunkId = $this->createRetryChunk($job, $chunk, $outputDisk, $retryStream, $retryCount);
        fclose($retryStream);

        $chunk->update([
            'risky_key' => $filteredKey,
            'risky_count' => $filteredCount,
            'email_count' => max(0, (int) ($chunk->valid_count ?? 0) + (int) ($chunk->invalid_count ?? 0) + $filteredCount),
        ]);

        return [
            'retry_count' => $retryCount,
            'tempfail_count' => $retryCount,
            'retry_chunk_id' => $retryChunkId,
        ];
    }

    private function filteredRiskyKey(string $key): string
    {
        if (str_ends_with($key, '.csv')) {
            return substr($key, 0, -4).'.filtered.csv';
        }

        return $key.'.filtered';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseLine(string $line): array
    {
        $columns = str_getcsv($line);
        if ($columns === []) {
            return ['', ''];
        }

        $email = trim((string) ($columns[0] ?? ''));
        $reason = '';

        if (count($columns) >= 5 && in_array(strtolower((string) ($columns[1] ?? '')), ['valid', 'invalid', 'risky'], true)) {
            $reason = trim((string) ($columns[4] ?? ''));
        } else {
            $reason = trim((string) ($columns[1] ?? ''));
        }

        return [$email, $reason];
    }

    /**
     * @param  array<int, string>  $retryReasons
     */
    private function isRetryReason(string $reason, array $retryReasons): bool
    {
        $reason = strtolower(trim($reason));
        if ($reason === '') {
            return false;
        }

        $base = explode(':', $reason, 2)[0];

        return in_array($base, $retryReasons, true);
    }

    private function writeStream(string $disk, string $key, $stream): void
    {
        if (! is_resource($stream)) {
            return;
        }

        rewind($stream);

        $stored = Storage::disk($disk)->writeStream($key, $stream);
        if ($stored === false) {
            rewind($stream);
            Storage::disk($disk)->put($key, stream_get_contents($stream));
        }
    }

    private function createRetryChunk(
        VerificationJob $job,
        VerificationJobChunk $chunk,
        string $outputDisk,
        $retryStream,
        int $retryCount
    ): ?string {
        if (! is_resource($retryStream)) {
            return null;
        }

        $backoff = $this->backoffForAttempt($chunk->retry_attempt + 1);
        $availableAt = now()->addMinutes($backoff);

        return DB::transaction(function () use ($job, $chunk, $outputDisk, $retryStream, $retryCount, $availableAt) {
            VerificationJob::query()
                ->where('id', $job->id)
                ->lockForUpdate()
                ->first();

            $nextChunkNo = (int) VerificationJobChunk::query()
                ->where('verification_job_id', $job->id)
                ->max('chunk_no');
            $nextChunkNo = $nextChunkNo + 1;

            $inputDisk = $chunk->input_disk ?: $outputDisk;
            $inputKey = $this->storage->chunkInputKey($job, $nextChunkNo, 'txt');

            $this->writeStream($inputDisk, $inputKey, $retryStream);

            $retryChunk = VerificationJobChunk::create([
                'verification_job_id' => $job->id,
                'chunk_no' => $nextChunkNo,
                'status' => 'pending',
                'input_disk' => $inputDisk,
                'input_key' => $inputKey,
                'email_count' => $retryCount,
                'retry_attempt' => $chunk->retry_attempt + 1,
                'retry_parent_id' => $chunk->id,
                'available_at' => $availableAt,
            ]);

            $job->addLog('tempfail_retry_scheduled', 'Tempfail retries scheduled.', [
                'chunk_id' => (string) $chunk->id,
                'retry_chunk_id' => (string) $retryChunk->id,
                'retry_count' => $retryCount,
                'retry_attempt' => $retryChunk->retry_attempt,
                'available_at' => $availableAt->toIso8601String(),
            ]);

            return (string) $retryChunk->id;
        });
    }

    private function backoffForAttempt(int $attempt): int
    {
        $schedule = EngineSettings::tempfailRetryBackoffMinutes();
        if ($schedule === []) {
            return 10;
        }

        $index = max(0, $attempt - 1);

        return $schedule[$index] ?? end($schedule) ?: 10;
    }
}
