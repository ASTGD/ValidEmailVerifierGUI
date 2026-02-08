<?php

namespace App\Services;

use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ScreeningToProbeChunkPlanner
{
    public function __construct(private JobStorage $storage) {}

    /**
     * @return array{candidate_count: int, hard_invalid_count: int, probe_chunk_id: string|null}
     */
    public function plan(VerificationJob $job, VerificationJobChunk $chunk, string $outputDisk): array
    {
        if ($outputDisk === '') {
            $outputDisk = $this->storage->disk();
        }

        $rows = [
            'valid' => $this->readResultRows($outputDisk, (string) $chunk->valid_key, 'valid'),
            'invalid' => $this->readResultRows($outputDisk, (string) $chunk->invalid_key, 'invalid'),
            'risky' => $this->readResultRows($outputDisk, (string) $chunk->risky_key, 'risky'),
        ];

        $hardInvalidReasons = $this->hardInvalidReasons();
        $hardInvalidRows = [];
        $candidateEmails = [];

        foreach (['valid', 'invalid', 'risky'] as $status) {
            foreach ($rows[$status] as $row) {
                $email = $this->normalizeEmail($row['email'] ?? '');
                if ($email === null) {
                    continue;
                }

                $reason = strtolower(trim((string) ($row['reason'] ?? '')));
                $reasonBase = explode(':', $reason, 2)[0];

                if ($status === 'invalid' && in_array($reasonBase, $hardInvalidReasons, true)) {
                    $hardInvalidRows[] = [
                        'email' => $email,
                        'reason' => $reasonBase,
                    ];

                    continue;
                }

                $candidateEmails[$email] = true;
            }
        }

        $candidateCount = count($candidateEmails);
        if ($candidateCount === 0) {
            return [
                'candidate_count' => 0,
                'hard_invalid_count' => count($hardInvalidRows),
                'probe_chunk_id' => null,
            ];
        }

        $probeChunkId = $this->createProbeChunk(
            $job,
            $chunk,
            $outputDisk,
            array_keys($candidateEmails)
        );

        $this->rewriteScreeningOutputs($outputDisk, $chunk, $hardInvalidRows);

        return [
            'candidate_count' => $candidateCount,
            'hard_invalid_count' => count($hardInvalidRows),
            'probe_chunk_id' => $probeChunkId,
        ];
    }

    /**
     * @param  array<int, array{email: string, reason: string}>  $hardInvalidRows
     */
    private function rewriteScreeningOutputs(string $disk, VerificationJobChunk $chunk, array $hardInvalidRows): void
    {
        $this->writeRows($disk, (string) $chunk->valid_key, []);
        $this->writeRows($disk, (string) $chunk->risky_key, []);
        $this->writeRows($disk, (string) $chunk->invalid_key, $hardInvalidRows);
    }

    /**
     * @param  array<int, string>  $candidateEmails
     */
    private function createProbeChunk(
        VerificationJob $job,
        VerificationJobChunk $chunk,
        string $disk,
        array $candidateEmails
    ): string {
        $stream = tmpfile();
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open temporary stream for probe candidates.');
        }

        try {
            foreach ($candidateEmails as $email) {
                fwrite($stream, $email.PHP_EOL);
            }

            return DB::transaction(function () use ($job, $chunk, $disk, $stream, $candidateEmails): string {
                VerificationJob::query()
                    ->where('id', $job->id)
                    ->lockForUpdate()
                    ->first();

                $existingChunkId = VerificationJobChunk::query()
                    ->where('verification_job_id', $job->id)
                    ->where('processing_stage', 'smtp_probe')
                    ->where('source_stage', 'screening')
                    ->where('parent_chunk_id', $chunk->id)
                    ->value('id');

                if (is_string($existingChunkId) && $existingChunkId !== '') {
                    return $existingChunkId;
                }

                $nextChunkNo = (int) VerificationJobChunk::query()
                    ->where('verification_job_id', $job->id)
                    ->max('chunk_no');
                $nextChunkNo++;

                $inputKey = $this->storage->chunkInputKey($job, $nextChunkNo, 'txt');
                $this->writeStream($disk, $inputKey, $stream);

                $probeChunk = VerificationJobChunk::create([
                    'verification_job_id' => $job->id,
                    'chunk_no' => $nextChunkNo,
                    'status' => 'pending',
                    'processing_stage' => 'smtp_probe',
                    'source_stage' => 'screening',
                    'parent_chunk_id' => $chunk->id,
                    'input_disk' => $disk,
                    'input_key' => $inputKey,
                    'email_count' => count($candidateEmails),
                ]);

                return (string) $probeChunk->id;
            });
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array<int, array{email: string, reason: string}>
     */
    private function readResultRows(string $disk, string $key, string $fallbackStatus): array
    {
        if ($key === '' || ! Storage::disk($disk)->exists($key)) {
            return [];
        }

        $stream = Storage::disk($disk)->readStream($key);
        if (! is_resource($stream)) {
            return [];
        }

        $rows = [];

        try {
            while (($line = fgets($stream)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $columns = str_getcsv($line);
                if ($columns === []) {
                    continue;
                }

                if ($this->isHeaderRow($columns)) {
                    continue;
                }

                $email = trim((string) ($columns[0] ?? ''));
                if ($email === '') {
                    continue;
                }

                $status = strtolower(trim((string) ($columns[1] ?? $fallbackStatus)));
                $reason = '';

                if (count($columns) >= 5 && in_array($status, ['valid', 'invalid', 'risky'], true)) {
                    $reason = trim((string) ($columns[4] ?? ''));
                } else {
                    $reason = trim((string) ($columns[1] ?? ''));
                    $status = $fallbackStatus;
                }

                $rows[] = [
                    'email' => $email,
                    'reason' => $reason,
                    'status' => $status,
                ];
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $columns
     */
    private function isHeaderRow(array $columns): bool
    {
        $firstColumn = strtolower(trim((string) ($columns[0] ?? '')));

        return $firstColumn === 'email';
    }

    /**
     * @param  array<int, array{email: string, reason: string}>  $rows
     */
    private function writeRows(string $disk, string $key, array $rows): void
    {
        if ($key === '') {
            throw new RuntimeException('Screening output key is missing for probe handoff rewrite.');
        }

        $stream = tmpfile();
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open temporary stream for screening output rewrite.');
        }

        try {
            fwrite($stream, "email,reason\n");
            foreach ($rows as $row) {
                fputcsv($stream, [$row['email'], $row['reason']]);
            }

            $this->writeStream($disk, $key, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeStream(string $disk, string $key, $stream): void
    {
        rewind($stream);
        $stored = Storage::disk($disk)->writeStream($key, $stream);
        if ($stored === false) {
            rewind($stream);
            $contents = stream_get_contents($stream);
            $fallbackStored = is_string($contents)
                ? Storage::disk($disk)->put($key, $contents)
                : false;

            if ($fallbackStored === false) {
                throw new RuntimeException(sprintf(
                    'Unable to persist probe handoff payload to storage key [%s] on disk [%s].',
                    $key,
                    $disk
                ));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function hardInvalidReasons(): array
    {
        $reasons = config('engine.screening_hard_invalid_reasons', ['syntax', 'mx_missing']);
        if (! is_array($reasons)) {
            return ['syntax', 'mx_missing'];
        }

        $normalized = [];
        foreach ($reasons as $reason) {
            $reason = strtolower(trim((string) $reason));
            if ($reason !== '') {
                $normalized[] = $reason;
            }
        }

        return $normalized === [] ? ['syntax', 'mx_missing'] : array_values(array_unique($normalized));
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
