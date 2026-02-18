<?php

namespace Tests\Feature;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Jobs\FinalizeVerificationJob;
use App\Jobs\WriteBackVerificationCacheJob;
use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\JobStorage;
use App\Services\VerificationResultsMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationJobFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeJob(string $disk = 's3'): VerificationJob
    {
        $customer = User::factory()->create();

        return VerificationJob::create([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.txt',
            'input_disk' => $disk,
            'input_key' => 'uploads/'.$customer->id.'/job/input.txt',
            'output_disk' => $disk,
        ]);
    }

    private function makeChunk(VerificationJob $job, int $chunkNo, array $overrides = []): VerificationJobChunk
    {
        return VerificationJobChunk::create(array_merge([
            'verification_job_id' => $job->id,
            'chunk_no' => $chunkNo,
            'status' => 'completed',
            'processing_stage' => 'screening',
            'input_disk' => $job->input_disk,
            'input_key' => 'chunks/'.$job->id.'/'.$chunkNo.'/input.txt',
        ], $overrides));
    }

    public function test_finalization_noop_when_chunks_incomplete(): void
    {
        Storage::fake('s3');

        $job = $this->makeJob();
        $this->makeChunk($job, 1, ['status' => 'pending']);

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertNull($job->valid_key);
    }

    public function test_finalization_waits_for_smtp_probe_chunks_after_screening(): void
    {
        Storage::fake('s3');

        $job = $this->makeJob();
        $this->makeChunk($job, 1, [
            'status' => 'completed',
            'processing_stage' => 'screening',
        ]);
        $this->makeChunk($job, 2, [
            'status' => 'pending',
            'processing_stage' => 'smtp_probe',
        ]);

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertNull($job->valid_key);
    }

    public function test_finalization_marks_job_failed_when_chunk_failed(): void
    {
        Storage::fake('s3');

        $job = $this->makeJob();
        $this->makeChunk($job, 1, ['status' => 'failed']);

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Failed, $job->status);
        $this->assertNotNull($job->finished_at);
    }

    public function test_finalization_merges_outputs_and_updates_counts(): void
    {
        Storage::fake('s3');

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $chunkOne = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        $chunkTwo = $this->makeChunk($job, 2, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 2, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 2, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 2, 'risky'),
        ]);

        Storage::disk('s3')->put($chunkOne->valid_key, "email,reason\nvalid-one@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunkTwo->valid_key, "email,reason\nvalid-two@example.com,rcpt_ok\n");

        Storage::disk('s3')->put($chunkOne->invalid_key, "email,reason\ninvalid-one@example.com,syntax\n");
        Storage::disk('s3')->put($chunkTwo->invalid_key, "email,reason\ninvalid-two@example.com,mx_missing\n");

        Storage::disk('s3')->put($chunkOne->risky_key, "email,reason\nrisky-one@example.com,catch_all\n");
        Storage::disk('s3')->put($chunkTwo->risky_key, "email,reason\nrisky-two@example.com,smtp_timeout\n");

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertSame(2, $job->valid_count);
        $this->assertSame(2, $job->invalid_count);
        $this->assertSame(2, $job->risky_count);
        $this->assertNotNull($job->valid_key);
        $this->assertTrue(Storage::disk('s3')->exists($job->valid_key));
        $this->assertStringContainsString('email,status,sub_status,score,reason', Storage::disk('s3')->get($job->valid_key));
    }

    public function test_finalization_queues_async_writeback_when_enabled_and_cache_misses_exist(): void
    {
        Storage::fake('s3');
        Bus::fake();

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeJob();
        $job->update([
            'cache_miss_key' => $storage->cacheMissKey($job),
        ]);

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\ninvalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\nrisky@example.com,smtp_timeout\n");

        app()->call([new FinalizeVerificationJob($job->id), 'handle']);

        $job->refresh();
        $metrics = $job->metrics()->first();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        Bus::assertDispatched(WriteBackVerificationCacheJob::class, function (WriteBackVerificationCacheJob $queuedJob) use ($job): bool {
            return $queuedJob->jobId === $job->id;
        });
        $this->assertNotNull($metrics);
        $this->assertSame('queued', $metrics?->writeback_status);
        $this->assertNotNull($metrics?->writeback_queued_at);
        $this->assertNull($metrics?->writeback_started_at);
        $this->assertNull($metrics?->writeback_finished_at);
    }

    public function test_finalization_marks_writeback_disabled_when_feature_is_off(): void
    {
        Storage::fake('s3');
        Bus::fake();

        EngineSetting::query()->update([
            'cache_writeback_enabled' => false,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeJob();
        $job->update([
            'cache_miss_key' => $storage->cacheMissKey($job),
        ]);

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\ninvalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\nrisky@example.com,smtp_timeout\n");

        app()->call([new FinalizeVerificationJob($job->id), 'handle']);

        $job->refresh();
        $metrics = $job->metrics()->first();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        Bus::assertNotDispatched(WriteBackVerificationCacheJob::class);
        $this->assertNotNull($metrics);
        $this->assertSame('disabled', $metrics?->writeback_status);
        $this->assertNotNull($metrics?->writeback_finished_at);
    }

    public function test_finalization_marks_writeback_skipped_when_no_cache_miss_file_exists(): void
    {
        Storage::fake('s3');
        Bus::fake();

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\ninvalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\nrisky@example.com,smtp_timeout\n");

        app()->call([new FinalizeVerificationJob($job->id), 'handle']);

        $job->refresh();
        $metrics = $job->metrics()->first();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        Bus::assertNotDispatched(WriteBackVerificationCacheJob::class);
        $this->assertNotNull($metrics);
        $this->assertSame('skipped', $metrics?->writeback_status);
        $this->assertNotNull($metrics?->writeback_finished_at);
    }

    public function test_finalization_is_idempotent(): void
    {
        Storage::fake('s3');

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\nvalid@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\ninvalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\nrisky@example.com,smtp_timeout\n");

        FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();
        $firstKey = $job->valid_key;
        $firstContent = Storage::disk('s3')->get($job->valid_key);

        FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();

        $this->assertSame($firstKey, $job->valid_key);
        $this->assertSame($firstContent, Storage::disk('s3')->get($job->valid_key));
    }

    public function test_finalization_includes_cached_outputs_when_present(): void
    {
        Storage::fake('s3');

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $cachedKey = $storage->cachedResultKey($job, 'valid');
        Storage::disk('s3')->put($cachedKey, "email,reason\ncached@example.com,smtp_connect_ok\n");

        $job->update([
            'cached_valid_key' => $cachedKey,
        ]);

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\nchunk@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\ninvalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\nrisky@example.com,smtp_timeout\n");

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();
        $content = Storage::disk('s3')->get($job->valid_key);

        $this->assertSame(2, $job->valid_count);
        $this->assertStringContainsString('cached@example.com', $content);
        $this->assertStringContainsString('chunk@example.com', $content);
    }

    public function test_finalization_runs_with_only_cached_outputs(): void
    {
        Storage::fake('s3');

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $job->update([
            'cached_valid_key' => $storage->cachedResultKey($job, 'valid'),
            'cached_invalid_key' => $storage->cachedResultKey($job, 'invalid'),
            'cached_risky_key' => $storage->cachedResultKey($job, 'risky'),
        ]);

        Storage::disk('s3')->put($job->cached_valid_key, "email,reason\nvalid@example.com,cache_hit\n");
        Storage::disk('s3')->put($job->cached_invalid_key, "email,reason\ninvalid@example.com,cache_hit\n");
        Storage::disk('s3')->put($job->cached_risky_key, "email,reason\nrisky@example.com,cache_miss\n");

        FinalizeVerificationJob::dispatchSync($job->id);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertSame(1, $job->valid_count);
        $this->assertSame(1, $job->invalid_count);
        $this->assertSame(1, $job->risky_count);
        $this->assertNotNull($job->valid_key);
    }

    public function test_single_check_finalization_maps_legacy_short_row_using_fallback_mapper(): void
    {
        Storage::fake('s3');

        $job = $this->makeJob();
        $job->update([
            'origin' => VerificationJobOrigin::SingleCheck,
        ]);

        $this->makeChunk($job, 1, [
            'status' => 'completed',
        ]);

        $legacyValidKey = 'results/jobs/'.$job->id.'/legacy-valid.csv';
        Storage::disk('s3')->put($legacyValidKey, implode("\n", [
            'email,reason',
            'single-check@example.com,smtp_connect_ok',
        ]));

        $merger = $this->createMock(VerificationResultsMerger::class);
        $merger->expects($this->once())
            ->method('merge')
            ->willReturn([
                'disk' => 's3',
                'keys' => [
                    'valid' => $legacyValidKey,
                    'invalid' => null,
                    'risky' => null,
                ],
                'counts' => [
                    'valid' => 1,
                    'invalid' => 0,
                    'risky' => 0,
                ],
                'missing' => [],
            ]);

        $this->app->instance(VerificationResultsMerger::class, $merger);

        app()->call([new FinalizeVerificationJob($job->id), 'handle']);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertSame('valid', $job->single_result_status);
        $this->assertSame('smtp_connect_ok', $job->single_result_sub_status);
        $this->assertSame('smtp_connect_ok', $job->single_result_reason);
    }
}
