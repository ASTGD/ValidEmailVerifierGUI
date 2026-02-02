<?php

namespace App\Services\EmailVerificationCache;

use App\Contracts\CacheWriteBackService;
use App\Models\VerificationJob;
use App\Services\JobMetricsRecorder;
use App\Support\EmailHashing;
use App\Support\EngineSettings;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DynamoDbCacheWriteBackService implements CacheWriteBackService
{
    private ?DynamoDbClient $client = null;
    private string $table;
    private string $keyAttribute;
    private string $resultAttribute;
    private string $datetimeAttribute;
    private float $lastWriteAt = 0.0;
    private bool $stopWrites = false;
    private JobMetricsRecorder $metricsRecorder;

    public function __construct(JobMetricsRecorder $metricsRecorder)
    {
        $config = (array) config('engine.cache_dynamodb', []);
        $this->metricsRecorder = $metricsRecorder;

        $this->table = (string) ($config['table'] ?? '');
        $this->keyAttribute = trim((string) ($config['key_attribute'] ?? 'email'));
        $this->resultAttribute = trim((string) ($config['result_attribute'] ?? 'result'));
        $this->datetimeAttribute = trim((string) ($config['datetime_attribute'] ?? 'DateTime'));

        if ($this->keyAttribute === '') {
            $this->keyAttribute = 'email';
        }

        if ($this->resultAttribute === '') {
            $this->resultAttribute = 'result';
        }

        if ($this->datetimeAttribute === '') {
            $this->datetimeAttribute = 'DateTime';
        }
    }

    /**
     * @param  array{disk: string, keys: array<string, string>}  $result
     * @return array{status: string, attempted: int, written: int}
     */
    public function writeBack(VerificationJob $job, array $result): array
    {
        if (! EngineSettings::cacheWritebackEnabled()) {
            return ['status' => 'disabled', 'attempted' => 0, 'written' => 0];
        }

        if (EngineSettings::cacheWritebackTestEnabled() && EngineSettings::cacheOnlyEnabled()) {
            return $this->writeBackTest($job, $result);
        }

        if ($this->table === '') {
            $this->handleFailure('DynamoDB table not configured for cache write-back.');

            return ['status' => 'missing_table', 'attempted' => 0, 'written' => 0];
        }

        $cacheMissKey = $job->cache_miss_key;

        if (! $cacheMissKey) {
            return ['status' => 'no_cache_miss', 'attempted' => 0, 'written' => 0];
        }

        $disk = (string) ($result['disk'] ?? ($job->output_disk ?: ($job->input_disk ?: config('filesystems.default'))));
        $missDisk = $job->input_disk ?: $disk;

        if (! Storage::disk($missDisk)->exists($cacheMissKey)) {
            return ['status' => 'cache_miss_missing', 'attempted' => 0, 'written' => 0];
        }

        $misses = $this->loadCacheMisses($missDisk, $cacheMissKey);

        if ($misses === []) {
            return ['status' => 'no_cache_miss', 'attempted' => 0, 'written' => 0];
        }

        $missTotal = count($misses);
        $this->updateWritebackProgress($job, 0, $missTotal);

        $statuses = EngineSettings::cacheWritebackStatuses();

        if ($statuses === []) {
            return ['status' => 'no_statuses', 'attempted' => 0, 'written' => 0];
        }

        $batchSize = EngineSettings::cacheWritebackBatchSize();
        $timestamp = Carbon::now()->toISOString();
        $batch = [];
        $attempted = 0;
        $written = 0;

        foreach ($statuses as $status) {
            $key = $result['keys'][$status] ?? null;

            if (! $key || ! Storage::disk($disk)->exists($key)) {
                continue;
            }

            $stream = Storage::disk($disk)->readStream($key);

            if (! is_resource($stream)) {
                continue;
            }

            try {
                while (($line = fgets($stream)) !== false) {
                    $email = $this->extractEmailFromLine($line);

                    if (! $email) {
                        continue;
                    }

                    $normalized = EmailHashing::normalizeEmail($email);

                    if ($normalized === '' || ! isset($misses[$normalized])) {
                        continue;
                    }

                    $batch[] = $this->makeWriteRequest($normalized, $status, $timestamp);
                    $attempted++;

                    if (count($batch) >= $batchSize) {
                        $written += $this->flushBatch($batch);
                        $batch = [];
                        $this->updateWritebackProgress($job, $written, $missTotal);

                        if ($this->stopWrites) {
                            break 2;
                        }
                    }
                }
            } finally {
                fclose($stream);
            }
        }

        if (! $this->stopWrites && $batch !== []) {
            $written += $this->flushBatch($batch);
        }

        $this->updateWritebackProgress($job, $written, $missTotal);

        return [
            'status' => $this->stopWrites ? 'skipped' : 'completed',
            'attempted' => $attempted,
            'written' => $written,
        ];
    }

    /**
     * @param  array{disk: string, keys: array<string, string>}  $result
     * @return array{status: string, attempted: int, written: int}
     */
    private function writeBackTest(VerificationJob $job, array $result): array
    {
        $testTable = EngineSettings::cacheWritebackTestTable();

        if (! $testTable) {
            $this->handleFailure('Cache write-back test table not configured.');

            return ['status' => 'missing_test_table', 'attempted' => 0, 'written' => 0];
        }

        $this->table = $testTable;

        $cacheMissKey = $job->cache_miss_key;

        if (! $cacheMissKey) {
            return ['status' => 'no_cache_miss', 'attempted' => 0, 'written' => 0];
        }

        $disk = (string) ($result['disk'] ?? ($job->output_disk ?: ($job->input_disk ?: config('filesystems.default'))));
        $missDisk = $job->input_disk ?: $disk;

        if (! Storage::disk($missDisk)->exists($cacheMissKey)) {
            return ['status' => 'cache_miss_missing', 'attempted' => 0, 'written' => 0];
        }

        $misses = $this->loadCacheMisses($missDisk, $cacheMissKey);

        if ($misses === []) {
            return ['status' => 'no_cache_miss', 'attempted' => 0, 'written' => 0];
        }

        $missTotal = count($misses);
        $this->updateWritebackProgress($job, 0, $missTotal);

        $batchSize = EngineSettings::cacheWritebackBatchSize();
        $timestamp = Carbon::now()->toISOString();
        $resultValue = EngineSettings::cacheWritebackTestResult();
        $batch = [];
        $attempted = 0;
        $written = 0;

        foreach (array_keys($misses) as $email) {
            $batch[] = $this->makeWriteRequest($email, $resultValue, $timestamp, false);
            $attempted++;

            if (count($batch) >= $batchSize) {
                $written += $this->flushBatch($batch);
                $batch = [];
                $this->updateWritebackProgress($job, $written, $missTotal);

                if ($this->stopWrites) {
                    break;
                }
            }
        }

        if (! $this->stopWrites && $batch !== []) {
            $written += $this->flushBatch($batch);
        }

        $this->updateWritebackProgress($job, $written, $missTotal);

        return [
            'status' => $this->stopWrites ? 'skipped' : 'completed',
            'attempted' => $attempted,
            'written' => $written,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function loadCacheMisses(string $disk, string $key): array
    {
        $stream = Storage::disk($disk)->readStream($key);

        if (! is_resource($stream)) {
            return [];
        }

        $misses = [];

        try {
            while (($line = fgets($stream)) !== false) {
                $email = trim($line);

                if ($email === '') {
                    continue;
                }

                $normalized = EmailHashing::normalizeEmail($email);

                if ($normalized === '') {
                    continue;
                }

                $misses[$normalized] = true;
            }
        } finally {
            fclose($stream);
        }

        return $misses;
    }

    private function extractEmailFromLine(string $line): ?string
    {
        $normalized = rtrim($line, "\r\n");

        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return null;
        }

        $columns = str_getcsv($normalized);

        if ($columns === []) {
            return null;
        }

        $email = trim((string) ($columns[0] ?? ''));

        return $email === '' ? null : $email;
    }

    /**
     * @param  array<int, array<string, array<string, array<string, string>>>>  $batch
     */
    private function flushBatch(array $batch): int
    {
        $pending = $batch;
        $attempt = 0;
        $written = 0;
        $maxAttempts = EngineSettings::cacheWritebackRetryAttempts();

        while ($pending !== []) {
            $attempt++;

            try {
                $unprocessed = $this->sendBatch($pending);
            } catch (AwsException $exception) {
                $this->handleFailure('Cache write-back failed: '.$exception->getAwsErrorMessage());

                return $written;
            }

            $written += count($pending) - count($unprocessed);

            if ($unprocessed === []) {
                return $written;
            }

            if ($attempt > $maxAttempts) {
                $this->handleFailure('Cache write-back unprocessed items remained after retries.');

                return $written;
            }

            $pending = $unprocessed;
            $this->sleepForBackoff($attempt);

            if ($this->stopWrites) {
                return $written;
            }
        }

        return $written;
    }

    private function updateWritebackProgress(VerificationJob $job, int $written, int $total): void
    {
        if ($total <= 0) {
            return;
        }

        $ratio = min(1, $written / $total);
        $progress = (int) round(90 + (10 * $ratio));

        $this->metricsRecorder->recordPhase($job, 'writeback', [
            'writeback_written_count' => $written,
            'cache_miss_count' => $total,
            'progress_percent' => min(100, $progress),
        ]);
    }

    /**
     * @param  array<int, array<string, array<string, array<string, string>>>>  $items
     * @return array<int, array<string, array<string, array<string, string>>>>
     */
    protected function sendBatch(array $items): array
    {
        $this->throttleBeforeWrite();

        $response = $this->client()->batchWriteItem([
            'RequestItems' => [
                $this->table => $items,
            ],
        ]);

        return $response['UnprocessedItems'][$this->table] ?? [];
    }

    private function client(): DynamoDbClient
    {
        if ($this->client) {
            return $this->client;
        }

        $config = (array) config('engine.cache_dynamodb', []);
        $region = (string) ($config['region'] ?? config('filesystems.disks.s3.region') ?? env('AWS_DEFAULT_REGION'));

        $clientConfig = [
            'version' => 'latest',
            'region' => $region,
        ];

        if (! empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        $this->client = new DynamoDbClient($clientConfig);

        return $this->client;
    }

    private function throttleBeforeWrite(): void
    {
        $maxWritesPerSecond = EngineSettings::cacheWritebackMaxWritesPerSecond();

        if ($maxWritesPerSecond) {
            $minInterval = 1 / $maxWritesPerSecond;
            if ($this->lastWriteAt > 0) {
                $elapsed = microtime(true) - $this->lastWriteAt;
                if ($elapsed < $minInterval) {
                    usleep((int) (($minInterval - $elapsed) * 1_000_000));
                }
            }
        }

        $this->lastWriteAt = microtime(true);
    }

    private function sleepForBackoff(int $attempt): void
    {
        $base = EngineSettings::cacheWritebackBackoffBaseMs();
        $max = EngineSettings::cacheWritebackBackoffMaxMs();

        if ($base <= 0 || $max <= 0) {
            return;
        }

        $delay = min($base * (2 ** max(0, $attempt - 1)), $max);

        usleep((int) $delay * 1000);
    }

    private function makeWriteRequest(string $email, string $status, string $timestamp, bool $normalizeResult = true): array
    {
        $normalizedStatus = $normalizeResult
            ? (strtolower($status) === 'invalid' ? 'Invalid' : 'Valid')
            : $status;

        return [
            'PutRequest' => [
                'Item' => [
                    $this->keyAttribute => ['S' => $email],
                    $this->resultAttribute => ['S' => $normalizedStatus],
                    $this->datetimeAttribute => ['S' => $timestamp],
                ],
            ],
        ];
    }

    private function handleFailure(string $message): void
    {
        $mode = EngineSettings::cacheWritebackFailureMode();

        if ($mode === 'fail_job') {
            throw new RuntimeException($message);
        }

        if ($mode === 'skip_writes') {
            $this->stopWrites = true;
        }
    }
}
