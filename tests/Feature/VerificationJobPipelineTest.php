<?php

namespace Tests\Feature;

use App\Contracts\EmailVerificationCacheStore;
use App\Enums\VerificationJobStatus;
use App\Jobs\ParseAndChunkJob;
use App\Jobs\PrepareVerificationJob;
use App\Models\EmailVerificationOutcome;
use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\EmailHashing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationJobPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_creates_chunks_and_updates_counts(): void
    {
        Storage::fake('local');

        config([
            'verifier.storage_disk' => 'local',
            'engine.chunk_size_default' => 2,
            'engine.cache_batch_size' => 2,
            'engine.max_emails_per_upload' => 0,
            'engine.dedupe_in_memory_limit' => 1000,
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'input.txt',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
        ]);

        $content = implode("\n", [
            'alpha@example.com',
            'beta@example.com',
            'alpha@example.com',
            'gamma@example.com',
            'delta@example.com',
            'epsilon@example.com',
        ]);

        Storage::disk('local')->put($job->input_key, $content);

        Bus::fake();

        app()->call([new PrepareVerificationJob($job->id), 'handle']);
        $job->refresh();

        $this->assertSame(VerificationJobStatus::Processing, $job->status);

        Bus::assertDispatched(ParseAndChunkJob::class);

        app()->call([new ParseAndChunkJob($job->id), 'handle']);
        $job->refresh();

        $this->assertSame(5, $job->total_emails);
        $this->assertSame(0, $job->cached_count);
        $this->assertSame(5, $job->unknown_count);
        $this->assertNotNull($job->prepared_at);

        $chunks = $job->chunks()->orderBy('chunk_no')->get();
        $this->assertCount(3, $chunks);
        $this->assertSame('chunks/'.$job->id.'/1/input.txt', $chunks->first()->input_key);
        $this->assertSame(2, $chunks->first()->email_count);
    }

    public function test_cache_store_is_called_in_batches(): void
    {
        Storage::fake('local');

        config([
            'verifier.storage_disk' => 'local',
            'engine.chunk_size_default' => 10,
            'engine.cache_batch_size' => 3,
            'engine.max_emails_per_upload' => 0,
            'engine.dedupe_in_memory_limit' => 1000,
        ]);

        $store = new class implements EmailVerificationCacheStore {
            public array $batches = [];

            public function lookupMany(array $emails): array
            {
                $this->batches[] = $emails;

                return [
                    'cached@example.com' => true,
                ];
            }
        };

        $this->app->instance(EmailVerificationCacheStore::class, $store);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'input.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $content = implode("\n", [
            'cached@example.com',
            'one@example.com',
            'two@example.com',
            'three@example.com',
            'four@example.com',
            'five@example.com',
            'six@example.com',
        ]);

        Storage::disk('local')->put($job->input_key, $content);

        Bus::fake();

        app()->call([new PrepareVerificationJob($job->id), 'handle']);
        Bus::assertDispatched(ParseAndChunkJob::class);

        app()->call([new ParseAndChunkJob($job->id), 'handle']);

        $job->refresh();

        $this->assertSame(7, $job->total_emails);
        $this->assertSame(1, $job->cached_count);
        $this->assertSame(6, $job->unknown_count);

        $this->assertCount(3, $store->batches);
        $this->assertSame(3, count($store->batches[0]));
        $this->assertSame(3, count($store->batches[1]));
        $this->assertSame(1, count($store->batches[2]));
    }

    public function test_pipeline_uses_database_cache_hits(): void
    {
        Storage::fake('local');

        config([
            'verifier.storage_disk' => 'local',
            'engine.cache_store_driver' => 'database',
            'engine.cache_freshness_days' => 30,
            'engine.chunk_size_default' => 10,
            'engine.cache_batch_size' => 10,
            'engine.max_emails_per_upload' => 0,
            'engine.dedupe_in_memory_limit' => 1000,
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'input.txt',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
        ]);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail('cached@example.com'),
            'email_normalized' => 'cached@example.com',
            'outcome' => 'valid',
            'reason_code' => 'delivered',
            'observed_at' => now()->subHour(),
        ]);

        $content = implode("\n", [
            'cached@example.com',
            'new@example.com',
        ]);

        Storage::disk('local')->put($job->input_key, $content);

        app()->call([new ParseAndChunkJob($job->id), 'handle']);
        $job->refresh();

        $this->assertSame(2, $job->total_emails);
        $this->assertSame(1, $job->cached_count);
        $this->assertSame(1, $job->unknown_count);

        $chunks = $job->chunks()->get();
        $this->assertCount(1, $chunks);

        $chunk = $chunks->first();
        $this->assertNotNull($chunk);

        $chunkContent = Storage::disk('local')->get($chunk->input_key);
        $this->assertStringContainsString('new@example.com', $chunkContent);
        $this->assertStringNotContainsString('cached@example.com', $chunkContent);

        $this->assertNotNull($job->cached_valid_key);
        $cachedContent = Storage::disk('local')->get($job->cached_valid_key);
        $this->assertStringContainsString("email,reason\n", $cachedContent);
        $this->assertStringContainsString('cached@example.com,delivered', $cachedContent);
    }

    public function test_cache_only_mode_skips_chunking_and_marks_misses(): void
    {
        Storage::fake('local');

        config([
            'verifier.storage_disk' => 'local',
            'engine.cache_batch_size' => 10,
            'engine.max_emails_per_upload' => 0,
            'engine.dedupe_in_memory_limit' => 1000,
            'engine.cache_only_mode_enabled' => true,
            'engine.cache_only_miss_status' => 'risky',
        ]);

        EngineSetting::query()->update([
            'cache_only_mode_enabled' => true,
            'cache_only_miss_status' => 'risky',
        ]);

        $store = new class implements EmailVerificationCacheStore {
            public function lookupMany(array $emails): array
            {
                return [
                    'hit@example.com' => [
                        'status' => 'valid',
                        'reason_code' => 'cache_hit',
                    ],
                ];
            }
        };

        $this->app->instance(EmailVerificationCacheStore::class, $store);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'input.txt',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.txt',
        ]);

        Storage::disk('local')->put($job->input_key, "hit@example.com\nmiss@example.com\n");

        app()->call([new ParseAndChunkJob($job->id), 'handle']);
        $job->refresh();

        $this->assertSame(2, $job->total_emails);
        $this->assertSame(1, $job->cached_count);
        $this->assertSame(0, $job->unknown_count);
        $this->assertCount(0, $job->chunks()->get());

        $this->assertNotNull($job->cached_valid_key);
        $this->assertNotNull($job->cached_risky_key);

        $validContent = Storage::disk('local')->get($job->cached_valid_key);
        $riskyContent = Storage::disk('local')->get($job->cached_risky_key);

        $this->assertStringContainsString('hit@example.com,cache_hit', $validContent);
        $this->assertStringContainsString('miss@example.com,cache_miss', $riskyContent);
    }
}
