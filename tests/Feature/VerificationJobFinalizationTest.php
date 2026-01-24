<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Jobs\FinalizeVerificationJob;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\JobStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
