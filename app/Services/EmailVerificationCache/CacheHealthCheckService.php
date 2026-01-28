<?php

namespace App\Services\EmailVerificationCache;

use App\Support\EmailHashing;
use Aws\DynamoDb\DynamoDbClient;
use Throwable;

class CacheHealthCheckService
{
    private string $table = '';
    private string $keyAttribute = 'email';

    /**
     * @param  array<int, string>|null  $readTestEmails
     * @return array{ok: bool, message: string, status?: string, item_count?: int, read_test?: array{attempted: int, found: int}}
     */
    public function check(?array $readTestEmails = null): array
    {
        $driver = (string) config('engine.cache_store_driver', 'null');

        if ($driver !== 'dynamodb') {
            return [
                'ok' => false,
                'message' => 'Cache driver is not set to DynamoDB.',
            ];
        }

        $config = (array) config('engine.cache_dynamodb', []);
        $table = (string) ($config['table'] ?? '');
        $this->table = $table;
        $this->keyAttribute = (string) ($config['key_attribute'] ?? 'email');

        if ($table === '') {
            return [
                'ok' => false,
                'message' => 'DynamoDB table is not configured.',
            ];
        }

        $region = (string) ($config['region'] ?? config('filesystems.disks.s3.region') ?? env('AWS_DEFAULT_REGION'));

        if ($region === '') {
            return [
                'ok' => false,
                'message' => 'AWS region is not configured.',
            ];
        }

        $clientConfig = [
            'version' => 'latest',
            'region' => $region,
        ];

        if (! empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        $readEmails = $this->normalizeReadEmails($readTestEmails);

        $client = null;

        try {
            $client = new DynamoDbClient($clientConfig);
            $response = $client->describeTable(['TableName' => $table]);
            $tableStatus = $response['Table']['TableStatus'] ?? null;
            $itemCount = $response['Table']['ItemCount'] ?? null;

            $message = 'Connected to DynamoDB.';
            if ($tableStatus) {
                $message .= ' Table status: '.$tableStatus.'.';
            }

            $result = [
                'ok' => true,
                'message' => $message,
                'status' => $tableStatus,
                'item_count' => is_numeric($itemCount) ? (int) $itemCount : null,
            ];

            if ($readEmails !== []) {
                $readResult = $this->runReadTest($client, $readEmails);

                if (! $readResult['ok']) {
                    return $readResult['response'];
                }

                $result['read_test'] = [
                    'attempted' => $readResult['attempted'],
                    'found' => $readResult['found'],
                ];
                $result['message'] .= ' Read test: '.$readResult['found'].' of '.$readResult['attempted'].' items found.';
            }

            return $result;
        } catch (Throwable $exception) {
            if ($readEmails !== []) {
                try {
                    $client = $client ?? new DynamoDbClient($clientConfig);
                    $readResult = $this->runReadTest($client, $readEmails);

                    if ($readResult['ok']) {
                        return [
                            'ok' => true,
                            'message' => 'Read test succeeded. DescribeTable failed: '.$exception->getMessage(),
                            'read_test' => [
                                'attempted' => $readResult['attempted'],
                                'found' => $readResult['found'],
                            ],
                        ];
                    }

                    return [
                        'ok' => false,
                        'message' => 'DescribeTable failed: '.$exception->getMessage().' Read test failed: '.($readResult['response']['message'] ?? 'Unknown error.'),
                    ];
                } catch (Throwable $readException) {
                    return [
                        'ok' => false,
                        'message' => 'DescribeTable failed: '.$exception->getMessage().' Read test failed: '.$readException->getMessage(),
                    ];
                }
            }

            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, string>|null  $emails
     * @return array<int, string>
     */
    private function normalizeReadEmails(?array $emails): array
    {
        if (! $emails) {
            return [];
        }

        $unique = [];

        foreach ($emails as $email) {
            $normalized = EmailHashing::normalizeEmail((string) $email);
            if ($normalized === '') {
                continue;
            }

            $unique[$normalized] = true;
        }

        return array_slice(array_keys($unique), 0, 25);
    }

    /**
     * @param  array<int, string>  $emails
     * @return array{ok: bool, attempted: int, found: int, response?: array{ok: bool, message: string}}
     */
    private function runReadTest(DynamoDbClient $client, array $emails): array
    {
        try {
            $response = $client->batchGetItem([
                'RequestItems' => [
                    $this->table => [
                        'Keys' => array_map(fn (string $email): array => [
                            $this->keyAttribute => ['S' => $email],
                        ], $emails),
                        'ProjectionExpression' => $this->keyAttribute,
                    ],
                ],
            ]);

            $items = $response['Responses'][$this->table] ?? [];

            return [
                'ok' => true,
                'attempted' => count($emails),
                'found' => is_array($items) ? count($items) : 0,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'attempted' => count($emails),
                'found' => 0,
                'response' => [
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }
}
