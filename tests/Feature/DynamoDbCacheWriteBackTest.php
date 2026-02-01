<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Services\EmailVerificationCache\DynamoDbCacheWriteBackService;
use App\Services\JobStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DynamoDbCacheWriteBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_writeback_filters_cache_misses(): void
    {
        Storage::fake('s3');

        config([
            'engine.cache_dynamodb.table' => 'emailresources',
        ]);

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
            'cache_writeback_statuses' => ['valid', 'invalid'],
            'cache_writeback_batch_size' => 2,
            'cache_writeback_failure_mode' => 'continue',
            'cache_writeback_retry_attempts' => 0,
            'cache_writeback_backoff_base_ms' => 0,
            'cache_writeback_backoff_max_ms' => 0,
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'original_filename' => 'emails.txt',
            'input_disk' => 's3',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
            'output_disk' => 's3',
        ]);

        $storage = app(JobStorage::class);
        $cacheMissKey = $storage->cacheMissKey($job);

        $job->update([
            'cache_miss_key' => $cacheMissKey,
        ]);

        Storage::disk('s3')->put($cacheMissKey, implode("\n", [
            'miss-one@example.com',
            'miss-two@example.com',
            '',
        ]));

        $validKey = $storage->finalResultKey($job, 'valid');
        $invalidKey = $storage->finalResultKey($job, 'invalid');

        Storage::disk('s3')->put($validKey, implode("\n", [
            'email,status,sub_status,score,reason',
            'miss-one@example.com,valid,smtp_connect_ok,90,smtp_connect_ok',
            'hit-one@example.com,valid,smtp_connect_ok,90,smtp_connect_ok',
        ]));

        Storage::disk('s3')->put($invalidKey, implode("\n", [
            'email,status,sub_status,score,reason',
            'miss-two@example.com,invalid,syntax,10,syntax',
            'hit-two@example.com,invalid,mx_missing,5,mx_missing',
        ]));

        $service = new class extends DynamoDbCacheWriteBackService {
            public array $captured = [];

            protected function sendBatch(array $items): array
            {
                foreach ($items as $item) {
                    $email = $item['PutRequest']['Item']['email']['S'] ?? null;

                    if ($email) {
                        $this->captured[] = $email;
                    }
                }

                return [];
            }
        };

        $service->writeBack($job, [
            'disk' => 's3',
            'keys' => [
                'valid' => $validKey,
                'invalid' => $invalidKey,
            ],
        ]);

        sort($service->captured);

        $this->assertSame([
            'miss-one@example.com',
            'miss-two@example.com',
        ], $service->captured);
    }

    public function test_writeback_test_mode_writes_cache_miss_result(): void
    {
        Storage::fake('s3');

        config([
            'engine.cache_dynamodb.table' => 'emailresources',
            'engine.cache_writeback_test_result' => 'Cache_miss',
        ]);

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
            'cache_only_mode_enabled' => true,
            'cache_writeback_test_mode_enabled' => true,
            'cache_writeback_test_table' => 'emailresources-test',
            'cache_writeback_batch_size' => 2,
            'cache_writeback_failure_mode' => 'continue',
            'cache_writeback_retry_attempts' => 0,
            'cache_writeback_backoff_base_ms' => 0,
            'cache_writeback_backoff_max_ms' => 0,
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'original_filename' => 'emails.txt',
            'input_disk' => 's3',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
            'output_disk' => 's3',
        ]);

        $storage = app(JobStorage::class);
        $cacheMissKey = $storage->cacheMissKey($job);

        $job->update([
            'cache_miss_key' => $cacheMissKey,
        ]);

        Storage::disk('s3')->put($cacheMissKey, implode("\n", [
            'miss-one@example.com',
            'miss-two@example.com',
        ]));

        $service = new class extends DynamoDbCacheWriteBackService {
            public array $captured = [];

            protected function sendBatch(array $items): array
            {
                foreach ($items as $item) {
                    $email = $item['PutRequest']['Item']['email']['S'] ?? null;
                    $result = $item['PutRequest']['Item']['result']['S'] ?? null;

                    if ($email) {
                        $this->captured[] = [$email, $result];
                    }
                }

                return [];
            }
        };

        $service->writeBack($job, [
            'disk' => 's3',
            'keys' => [],
        ]);

        sort($service->captured);

        $this->assertSame([
            ['miss-one@example.com', 'Cache_miss'],
            ['miss-two@example.com', 'Cache_miss'],
        ], $service->captured);
    }

    public function test_writeback_disabled_skips_writes(): void
    {
        Storage::fake('s3');

        config([
            'engine.cache_dynamodb.table' => 'emailresources',
        ]);

        EngineSetting::query()->update([
            'cache_writeback_enabled' => false,
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'original_filename' => 'emails.txt',
            'input_disk' => 's3',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
            'output_disk' => 's3',
        ]);

        $storage = app(JobStorage::class);
        $cacheMissKey = $storage->cacheMissKey($job);

        $job->update([
            'cache_miss_key' => $cacheMissKey,
        ]);

        Storage::disk('s3')->put($cacheMissKey, "miss@example.com\n");

        $validKey = $storage->finalResultKey($job, 'valid');
        Storage::disk('s3')->put($validKey, implode("\n", [
            'email,status,sub_status,score,reason',
            'miss@example.com,valid,smtp_connect_ok,90,smtp_connect_ok',
        ]));

        $service = new class extends DynamoDbCacheWriteBackService {
            public array $captured = [];

            protected function sendBatch(array $items): array
            {
                foreach ($items as $item) {
                    $email = $item['PutRequest']['Item']['email']['S'] ?? null;

                    if ($email) {
                        $this->captured[] = $email;
                    }
                }

                return [];
            }
        };

        $service->writeBack($job, [
            'disk' => 's3',
            'keys' => [
                'valid' => $validKey,
            ],
        ]);

        $this->assertSame([], $service->captured);
    }
}
