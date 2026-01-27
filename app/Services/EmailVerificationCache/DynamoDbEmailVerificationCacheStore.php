<?php

namespace App\Services\EmailVerificationCache;

use App\Contracts\EmailVerificationCacheStore;
use App\Support\EmailHashing;
use App\Support\EngineSettings;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\Carbon;

class DynamoDbEmailVerificationCacheStore implements EmailVerificationCacheStore
{
    private DynamoDbClient $client;
    private string $table;
    private string $keyAttribute;
    private string $resultAttribute;
    private ?string $datetimeAttribute;

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

        $cutoff = null;
        $freshnessDays = (int) config('engine.cache_freshness_days', config('verifier.cache_freshness_days', 30));

        if (! EngineSettings::cacheOnlyEnabled()) {
            if ($freshnessDays <= 0) {
                return [];
            }

            $cutoff = Carbon::now()->subDays($freshnessDays);
        }
        $results = [];

        foreach (array_chunk($normalized, 100) as $batch) {
            $request = [
                $this->table => [
                    'Keys' => array_map(fn (string $email): array => [
                        $this->keyAttribute => ['S' => $email],
                    ], $batch),
                ],
            ];

            $unprocessed = $request;

            for ($attempt = 0; $attempt < 3 && $unprocessed !== []; $attempt++) {
                $response = $this->client->batchGetItem([
                    'RequestItems' => $unprocessed,
                ]);

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

                $unprocessed = $response['UnprocessedKeys'] ?? [];

                if ($unprocessed !== []) {
                    usleep(200000);
                }
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
}
