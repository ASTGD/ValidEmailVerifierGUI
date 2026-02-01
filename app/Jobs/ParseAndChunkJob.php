<?php

namespace App\Jobs;

use App\Contracts\EmailVerificationCacheStore;
use App\Enums\VerificationJobStatus;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\EmailDedupeStore;
use App\Services\JobStorage;
use App\Support\EngineSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Throwable;

class ParseAndChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private int $chunkSize;
    private int $maxEmails;
    private int $cacheBatchSize;
    private int $dedupeMemoryLimit;
    private int $xlsxRowBatchSize;

    private int $totalUnique = 0;
    private int $cachedCount = 0;
    private int $unknownCount = 0;
    private int $chunksCreated = 0;
    private array $cachedCounts = [
        'valid' => 0,
        'invalid' => 0,
        'risky' => 0,
    ];
    private array $cachedStreams = [];
    private array $cachedKeys = [];
    private $cacheMissStream = null;
    private ?string $cacheMissKey = null;

    private ?VerificationJob $verificationJob = null;
    private ?EmailDedupeStore $deduper = null;
    private array $batch = [];
    private bool $cacheOnlyMode = false;
    private string $cacheOnlyMissStatus = 'risky';
    private string $cacheFailureMode = 'fail_job';
    private bool $skipCache = false;
    private bool $cacheWritebackTestEnabled = false;

    private $chunkStream = null;
    private int $chunkNo = 1;
    private int $chunkEmailCount = 0;

    public function __construct(public string $jobId)
    {
    }

    public function handle(JobStorage $storage, EmailVerificationCacheStore $cacheStore): void
    {
        $this->verificationJob = VerificationJob::query()->find($this->jobId);

        if (! $this->verificationJob || $this->verificationJob->status !== VerificationJobStatus::Processing) {
            return;
        }

        $this->configure();
        $this->deduper = new EmailDedupeStore($this->dedupeMemoryLimit);

        // Control-plane only: avoid MX/DNS/SMTP checks here; engine workers handle those.
        try {
            $this->verificationJob->addLog('parse_started', 'Parsing input file for verification pipeline.');

            $disk = $this->verificationJob->input_disk ?: $storage->disk();
            $key = $this->verificationJob->input_key;

            if (! Storage::disk($disk)->exists($key)) {
                $this->failJob('Input file not found on storage disk.', [
                    'disk' => $disk,
                    'key' => $key,
                ]);

                return;
            }

            $extension = $this->detectExtension();

            if (in_array($extension, ['xls', 'xlsx'], true)) {
                $this->parseSpreadsheet($disk, $key, $extension, $cacheStore, $storage);
            } else {
                $this->parseTextStream($disk, $key, $extension, $cacheStore, $storage);
            }

            $this->flushBatch($cacheStore, $storage, $disk);
            $this->finalizeChunk($storage, $disk);
            $this->finalizeCachedOutputs($storage, $disk);
            $this->finalizeCacheMissList($storage, $disk);

            $this->verificationJob->update([
                'total_emails' => $this->totalUnique,
                'cached_count' => $this->cachedCount,
                'unknown_count' => $this->unknownCount,
                'prepared_at' => now(),
                'cached_valid_key' => $this->cachedKeys['valid'] ?? null,
                'cached_invalid_key' => $this->cachedKeys['invalid'] ?? null,
                'cached_risky_key' => $this->cachedKeys['risky'] ?? null,
                'cache_miss_key' => $this->cacheMissKey,
            ]);

            $this->verificationJob->addLog('parse_completed', 'Input parsing complete.', [
                'total_unique' => $this->totalUnique,
                'cached_count' => $this->cachedCount,
                'unknown_count' => $this->unknownCount,
                'chunks_created' => $this->chunksCreated,
                'cached_counts' => $this->cachedCounts,
            ]);

            if ($this->cacheOnlyMode && $this->chunksCreated === 0) {
                $this->verificationJob->addLog('finalize_queued', 'Finalization queued for cache-only run.');
                FinalizeVerificationJob::dispatch($this->verificationJob->id);
            }
        } catch (Throwable $exception) {
            $this->failJob('Failed to prepare verification job.', [
                'error' => $exception->getMessage(),
            ]);
        } finally {
            if ($this->deduper) {
                $this->deduper->cleanup();
            }
        }
    }

    private function configure(): void
    {
        $this->chunkSize = max(1, (int) $this->engineConfig('chunk_size_default', 5000));
        $this->maxEmails = max(0, (int) $this->engineConfig('max_emails_per_upload', 0));
        $this->cacheBatchSize = EngineSettings::cacheBatchSize();
        $this->dedupeMemoryLimit = max(0, (int) $this->engineConfig('dedupe_in_memory_limit', 100000));
        $this->xlsxRowBatchSize = max(100, (int) $this->engineConfig('xlsx_row_batch_size', 1000));
        $this->cacheOnlyMode = EngineSettings::cacheOnlyEnabled();
        $this->cacheOnlyMissStatus = EngineSettings::cacheOnlyMissStatus();
        $this->cacheFailureMode = EngineSettings::cacheFailureMode();
        $this->cacheWritebackTestEnabled = EngineSettings::cacheWritebackTestEnabled();
    }

    private function engineConfig(string $key, mixed $fallback = null): mixed
    {
        $value = config('engine.'.$key);

        if ($value === null) {
            $value = config('verifier.'.$key, $fallback);
        }

        return $value ?? $fallback;
    }

    private function detectExtension(): string
    {
        $filename = $this->verificationJob?->original_filename ?: $this->verificationJob?->input_key;
        $extension = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));

        return $extension ?: 'txt';
    }

    private function parseTextStream(string $disk, string $key, string $extension, EmailVerificationCacheStore $cacheStore, JobStorage $storage): void
    {
        $stream = Storage::disk($disk)->readStream($key);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to open input stream.');
        }

        while (($line = fgets($stream)) !== false) {
            $emails = $this->extractEmailsFromLine($line, $extension === 'csv');
            foreach ($emails as $email) {
                $this->handleCandidate($email, $cacheStore, $storage, $disk);
            }
        }

        fclose($stream);
    }

    private function parseSpreadsheet(string $disk, string $key, string $extension, EmailVerificationCacheStore $cacheStore, JobStorage $storage): void
    {
        $tempPath = $this->downloadToTempFile($disk, $key, $extension);
        $reader = IOFactory::createReaderForFile($tempPath);
        $reader->setReadDataOnly(true);

        $highestRow = $this->getHighestRow($reader, $tempPath);

        for ($startRow = 1; $startRow <= $highestRow; $startRow += $this->xlsxRowBatchSize) {
            $filter = new class($startRow, $this->xlsxRowBatchSize) implements IReadFilter {
                public function __construct(private int $startRow, private int $chunkSize)
                {
                }

                public function readCell($column, $row, $worksheetName = ''): bool
                {
                    return $row >= $this->startRow && $row < ($this->startRow + $this->chunkSize);
                }
            };

            $reader->setReadFilter($filter);
            $spreadsheet = $reader->load($tempPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $endRow = min($highestRow, $startRow + $this->xlsxRowBatchSize - 1);
            foreach ($worksheet->getRowIterator($startRow, $endRow) as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $cells[] = (string) $cell->getValue();
                }

                $emails = $this->extractEmailsFromRow($cells);
                foreach ($emails as $email) {
                    $this->handleCandidate($email, $cacheStore, $storage, $disk);
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->flushBatch($cacheStore, $storage, $disk);
        }

        @unlink($tempPath);
    }

    private function extractEmailsFromLine(string $line, bool $isCsv): array
    {
        $emails = [];

        if ($isCsv) {
            $columns = str_getcsv($line);
            $first = trim((string) Arr::get($columns, 0, ''));
            if ($this->looksLikeEmail($first)) {
                $emails[] = $first;
            }
        }

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line, $matches)) {
            $emails = array_merge($emails, $matches[0]);
        }

        return array_values(array_unique($emails));
    }

    private function extractEmailsFromRow(array $cells): array
    {
        $emails = [];
        $first = trim((string) Arr::get($cells, 0, ''));

        if ($this->looksLikeEmail($first)) {
            $emails[] = $first;
        }

        $line = implode(' ', array_filter(array_map('strval', $cells)));

        if ($line !== '' && preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line, $matches)) {
            $emails = array_merge($emails, $matches[0]);
        }

        return array_values(array_unique($emails));
    }

    private function handleCandidate(string $email, EmailVerificationCacheStore $cacheStore, JobStorage $storage, string $disk): void
    {
        $normalized = $this->normalizeEmail($email);

        if (! $normalized) {
            return;
        }

        if (! $this->deduper->isNew($normalized)) {
            return;
        }

        $this->totalUnique++;

        if ($this->maxEmails > 0 && $this->totalUnique > $this->maxEmails) {
            throw new \RuntimeException('Upload exceeds maximum allowed emails.');
        }

        $this->batch[] = $normalized;

        if (count($this->batch) >= $this->cacheBatchSize) {
            $this->flushBatch($cacheStore, $storage, $disk);
        }
    }

    private function flushBatch(EmailVerificationCacheStore $cacheStore, JobStorage $storage, string $disk): void
    {
        if ($this->batch === []) {
            return;
        }

        $batchSize = count($this->batch);

        $this->verificationJob?->addLog('cache_lookup_started', 'Cache lookup started.', [
            'batch_size' => $batchSize,
        ]);

        if ($this->skipCache) {
            $hits = [];
        } else {
            try {
                $hits = $cacheStore->lookupMany($this->batch);
            } catch (Throwable $exception) {
                $this->verificationJob?->addLog('cache_lookup_failed', 'Cache lookup failed.', [
                    'batch_size' => $batchSize,
                    'error' => $exception->getMessage(),
                ]);

                if ($this->cacheFailureMode === 'treat_miss') {
                    $hits = [];
                } elseif ($this->cacheFailureMode === 'skip_cache') {
                    $this->skipCache = true;
                    $this->cacheOnlyMode = false;
                    $hits = [];
                    $this->verificationJob?->addLog('cache_lookup_skipped', 'Cache disabled after failure; continuing without cache.', [
                        'batch_size' => $batchSize,
                    ]);
                } else {
                    throw $exception;
                }
            }
        }
        $hitCount = count($hits);

        $this->verificationJob?->addLog('cache_hits_found', 'Cache hits found.', [
            'batch_size' => $batchSize,
            'hit_count' => $hitCount,
        ]);

        foreach ($this->batch as $email) {
            if (array_key_exists($email, $hits)) {
                $this->cachedCount++;
                $this->handleCacheHit($email, $hits[$email], $storage, $disk);
                continue;
            }

            if ($this->cacheOnlyMode) {
                $this->handleCacheMiss($email, $storage, $disk);
            } else {
                $this->recordCacheMiss($email, $storage, $disk);
                $this->writeToChunk($email, $storage, $disk);
            }
        }

        $this->batch = [];

        $this->verificationJob?->addLog('cache_lookup_completed', 'Cache lookup completed.', [
            'batch_size' => $batchSize,
            'hit_count' => $hitCount,
        ]);
    }

    private function writeToChunk(string $email, JobStorage $storage, string $disk): void
    {
        if (! $this->chunkStream) {
            $this->chunkStream = tmpfile();
            $this->chunkEmailCount = 0;
        }

        fwrite($this->chunkStream, $email.PHP_EOL);
        $this->chunkEmailCount++;
        $this->unknownCount++;

        if ($this->chunkEmailCount >= $this->chunkSize) {
            $this->finalizeChunk($storage, $disk);
        }
    }

    private function finalizeChunk(JobStorage $storage, string $disk): void
    {
        if (! $this->chunkStream || $this->chunkEmailCount === 0) {
            return;
        }

        $key = $storage->chunkInputKey($this->verificationJob, $this->chunkNo, 'txt');

        rewind($this->chunkStream);
        Storage::disk($disk)->put($key, $this->chunkStream);
        fclose($this->chunkStream);

        VerificationJobChunk::create([
            'verification_job_id' => $this->verificationJob->id,
            'chunk_no' => $this->chunkNo,
            'status' => 'pending',
            'input_disk' => $disk,
            'input_key' => $key,
            'email_count' => $this->chunkEmailCount,
        ]);

        $this->chunksCreated++;
        $this->chunkNo++;
        $this->chunkStream = null;
        $this->chunkEmailCount = 0;
    }

    private function handleCacheHit(string $email, mixed $hit, JobStorage $storage, string $disk): void
    {
        if (! is_array($hit)) {
            return;
        }

        $status = strtolower((string) ($hit['status'] ?? $hit['outcome'] ?? ''));

        if (! in_array($status, ['valid', 'invalid', 'risky'], true)) {
            return;
        }

        $reason = trim((string) ($hit['reason_code'] ?? ''));
        $fallbackLine = $reason !== '' ? $email.','.$reason : $email.',';
        $line = (string) ($hit['row'] ?? $fallbackLine);

        $this->writeCached($status, $line, $storage, $disk);
        $this->cachedCounts[$status]++;
    }

    private function handleCacheMiss(string $email, JobStorage $storage, string $disk): void
    {
        if ($this->cacheWritebackTestEnabled) {
            $this->recordCacheMiss($email, $storage, $disk);
        }

        $status = in_array($this->cacheOnlyMissStatus, ['valid', 'invalid', 'risky'], true)
            ? $this->cacheOnlyMissStatus
            : 'risky';

        $line = $email.',cache_miss';

        $this->writeCached($status, $line, $storage, $disk);
        $this->cachedCounts[$status]++;
    }

    private function writeCached(string $status, string $line, JobStorage $storage, string $disk): void
    {
        if (! isset($this->cachedStreams[$status])) {
            $this->cachedStreams[$status] = tmpfile();
            $this->cachedKeys[$status] = $storage->cachedResultKey($this->verificationJob, $status);
            fwrite($this->cachedStreams[$status], "email,reason\n");
        }

        $normalizedLine = rtrim($line, "\r\n").PHP_EOL;
        fwrite($this->cachedStreams[$status], $normalizedLine);
    }

    private function finalizeCachedOutputs(JobStorage $storage, string $disk): void
    {
        foreach ($this->cachedStreams as $status => $stream) {
            $key = $this->cachedKeys[$status] ?? null;

            if (! $key || ! is_resource($stream)) {
                continue;
            }

            rewind($stream);
            Storage::disk($disk)->put($key, $stream);
            fclose($stream);
        }
    }

    private function recordCacheMiss(string $email, JobStorage $storage, string $disk): void
    {
        if (! $this->cacheMissStream) {
            $this->cacheMissStream = tmpfile();
            $this->cacheMissKey = $storage->cacheMissKey($this->verificationJob);
        }

        fwrite($this->cacheMissStream, $email.PHP_EOL);
    }

    private function finalizeCacheMissList(JobStorage $storage, string $disk): void
    {
        if (! $this->cacheMissStream || ! is_resource($this->cacheMissStream) || ! $this->cacheMissKey) {
            return;
        }

        rewind($this->cacheMissStream);
        Storage::disk($disk)->put($this->cacheMissKey, $this->cacheMissStream);
        fclose($this->cacheMissStream);
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $email = strtolower($email);
        $email = trim($email, " \t\n\r\0\x0B,;'\"");

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function looksLikeEmail(string $value): bool
    {
        return $value !== '' && str_contains($value, '@');
    }

    private function downloadToTempFile(string $disk, string $key, string $extension): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'verifier-upload-');
        $stream = Storage::disk($disk)->readStream($key);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to open input stream.');
        }

        $target = fopen($tempPath, 'w+b');
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        $finalPath = $tempPath.'.'.$extension;
        rename($tempPath, $finalPath);

        return $finalPath;
    }

    private function getHighestRow($reader, string $path): int
    {
        if (method_exists($reader, 'listWorksheetInfo')) {
            $info = $reader->listWorksheetInfo($path);

            if (! empty($info[0]['totalRows'])) {
                return (int) $info[0]['totalRows'];
            }
        }

        $reader->setReadFilter(null);
        $spreadsheet = $reader->load($path);
        $highestRow = $spreadsheet->getActiveSheet()->getHighestRow();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return (int) $highestRow;
    }

    private function failJob(string $message, array $context = []): void
    {
        if (! $this->verificationJob) {
            return;
        }

        $this->verificationJob->update([
            'status' => VerificationJobStatus::Failed,
            'error_message' => $message,
            'failure_source' => VerificationJob::FAILURE_SOURCE_SYSTEM,
            'finished_at' => now(),
        ]);

        $this->verificationJob->addLog('prepare_failed', $message, $context);
    }
}
