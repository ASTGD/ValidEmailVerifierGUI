<?php

namespace App\Services\EmailVerificationCache;

use App\Contracts\EmailVerificationCacheStore;
use App\Support\EmailHashing;
use App\Support\EngineSettings;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Carbon;

class DynamoDbEmailVerificationCacheStore implements EmailVerificationCacheStore
{
    private DynamoDbClient $client;
    private string $table;
    private string $keyAttribute;
    private string $resultAttribute;
    private ?string $datetimeAttribute;
    private float $lastBatchAt = 0.0;

    public function __construct()
    {
        $config = (array) config('engine.cache_dynamodb', []);

        $this->table = (string) ($config['table'] ?? '');
        $this->keyAttribute = (string) ($config['key_attribute'] ?? 'email');
        $this->resultAttribute = (string) ($config['result_attribute'] ?? 'result');
        $this->datetimeAttribute = $config['datetime_attribute'] ?? null;

        $region = (string) ($config['region'] ?? config('filesystems.disks.s3.region') ?? env('AWS_DEFAULT_REGION'));

        $clientConfig = [
            'version' => 'latest',
            'region' => $region,
        ];

        if (! empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        $this->client = new DynamoDbClient($clientConfig);
    }

    public function lookupMany(array $emails): array
    {
        if ($this->table === '') {
            return [];
        }

        $normalized = $this->normalizeEmails($emails);

        if ($normalized === []) {
            return [];
        }

        $mode = EngineSettings::cacheCapacityMode();
        $batchSize = EngineSettings::cacheBatchSize();
        $consistentRead = EngineSettings::cacheConsistentRead();

        $cutoff = null;
        $freshnessDays = (int) config('engine.cache_freshness_days', config('verifier.cache_freshness_days', 30));

        if (! EngineSettings::cacheOnlyEnabled()) {
            if ($freshnessDays <= 0) {
                return [];
            }

            $cutoff = Carbon::now()->subDays($freshnessDays);
        }
        $results = [];

        foreach (array_chunk($normalized, $batchSize) as $batch) {
            $this->throttleBeforeBatch($mode);

            $request = [
                $this->table => [
                    'Keys' => array_map(fn (string $email): array => [
                        $this->keyAttribute => ['S' => $email],
                    ], $batch),
                    'ConsistentRead' => $consistentRead,
                ],
            ];

            $response = $this->batchGetWithRetries($request, $mode);
            $items = $response['Responses'][$this->table] ?? [];

            foreach ($items as $item) {
                $email = $item[$this->keyAttribute]['S'] ?? null;
                if (! $email) {
                    continue;
                }

                $normalizedEmail = EmailHashing::normalizeEmail($email);
                if ($normalizedEmail === '') {
                    continue;
                }

                $status = $this->normalizeStatus($item[$this->resultAttribute]['S'] ?? '');
                if ($status === null) {
                    continue;
                }

                $observedAt = $this->parseObservedAt($item);
                if ($cutoff && $observedAt && $observedAt->lt($cutoff)) {
                    continue;
                }

                $results[$normalizedEmail] = [
                    'outcome' => $status,
                    'status' => $status,
                    'reason_code' => 'cache_hit',
                    'observed_at' => $observedAt?->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private function normalizeEmails(array $emails): array
    {
        $unique = [];

        foreach ($emails as $email) {
            $normalized = EmailHashing::normalizeEmail((string) $email);
            if ($normalized === '') {
                continue;
            }

            $unique[$normalized] = true;
        }

        return array_keys($unique);
    }

    private function normalizeStatus(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));

        return in_array($normalized, ['valid', 'invalid', 'risky'], true) ? $normalized : null;
    }

    private function parseObservedAt(array $item): ?Carbon
    {
        if (! $this->datetimeAttribute) {
            return null;
        }

        $raw = $item[$this->datetimeAttribute]['S'] ?? $item[$this->datetimeAttribute]['N'] ?? null;
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function throttleBeforeBatch(string $mode): void
    {
        $maxBatchesPerSecond = $mode === 'provisioned'
            ? EngineSettings::cacheProvisionedMaxBatchesPerSecond()
            : EngineSettings::cacheOnDemandMaxBatchesPerSecond();

        if ($maxBatchesPerSecond) {
            $minInterval = 1 / $maxBatchesPerSecond;
            if ($this->lastBatchAt > 0) {
                $elapsed = microtime(true) - $this->lastBatchAt;
                if ($elapsed < $minInterval) {
                    usleep((int) (($minInterval - $elapsed) * 1_000_000));
                }
            }
        }

        $sleepMs = $mode === 'provisioned'
            ? EngineSettings::cacheProvisionedSleepMsBetweenBatches()
            : EngineSettings::cacheOnDemandSleepMsBetweenBatches();

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }

        $this->lastBatchAt = microtime(true);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function batchGetWithRetries(array $request, string $mode): array
    {
        $maxRetries = $mode === 'provisioned'
            ? EngineSettings::cacheProvisionedMaxRetries()
            : 1;

        $attempt = 0;
        $pending = $request;
        $responses = [];

        while ($pending !== [] && $attempt <= $maxRetries) {
            $attempt++;

            try {
                $response = $this->client->batchGetItem(['RequestItems' => $pending]);
            } catch (AwsException $exception) {
                if ($this->isThrottleException($exception) && $attempt <= $maxRetries) {
                    $this->sleepForBackoff($attempt, $mode);
                    continue;
                }

                throw $exception;
            }

            $responses[] = $response;
            $pending = $response['UnprocessedKeys'] ?? [];

            if ($pending !== [] && $attempt <= $maxRetries) {
                $this->sleepForBackoff($attempt, $mode);
            }
        }

        return $this->mergeBatchResponses($responses);
    }

    private function isThrottleException(AwsException $exception): bool
    {
        $code = $exception->getAwsErrorCode();

        return in_array($code, [
            'ProvisionedThroughputExceededException',
            'ThrottlingException',
            'Throttling',
            'RequestLimitExceeded',
        ], true);
    }

    private function sleepForBackoff(int $attempt, string $mode): void
    {
        if ($mode !== 'provisioned') {
            return;
        }

        $base = EngineSettings::cacheProvisionedBackoffBaseMs();
        $max = EngineSettings::cacheProvisionedBackoffMaxMs();

        if ($base <= 0 || $max <= 0) {
            return;
        }

        $delay = min($base * (2 ** max(0, $attempt - 1)), $max);

        if (EngineSettings::cacheProvisionedJitterEnabled()) {
            $min = (int) max(1, $delay * 0.5);
            $delay = random_int($min, (int) $delay);
        }

        usleep((int) $delay * 1000);
    }

    /**
     * @param  array<int, array<string, mixed>>  $responses
     * @return array<string, mixed>
     */
    private function mergeBatchResponses(array $responses): array
    {
        $merged = [
            'Responses' => [
                $this->table => [],
            ],
        ];

        foreach ($responses as $response) {
            $items = $response['Responses'][$this->table] ?? [];
            if ($items !== []) {
                $merged['Responses'][$this->table] = array_merge($merged['Responses'][$this->table], $items);
            }
        }

        return $merged;
    }
}
