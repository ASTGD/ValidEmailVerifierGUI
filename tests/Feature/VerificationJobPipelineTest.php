<?php

namespace Tests\Feature;

use App\Contracts\EmailVerificationCacheStore;
use App\Enums\VerificationJobStatus;
use App\Jobs\ParseAndChunkJob;
use App\Jobs\PrepareVerificationJob;
use App\Models\User;
use App\Models\VerificationJob;
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
            'verifier.chunk_size_default' => 2,
            'verifier.cache_batch_size' => 2,
            'verifier.max_emails_per_upload' => 0,
            'verifier.dedupe_in_memory_limit' => 1000,
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
            'verifier.chunk_size_default' => 10,
            'verifier.cache_batch_size' => 3,
            'verifier.max_emails_per_upload' => 0,
            'verifier.dedupe_in_memory_limit' => 1000,
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
}
