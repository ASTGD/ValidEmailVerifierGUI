<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\EmailVerificationOutcome;
use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\JobStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationResultsMergerScoreTest extends TestCase
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

    public function test_catch_all_default_policy_keeps_risky(): void
    {
        Storage::fake('s3');

        config([
            'engine.catch_all_policy' => 'risky_only',
            'engine.catch_all_promote_threshold' => null,
        ]);

        EngineSetting::query()->update([
            'catch_all_policy' => 'risky_only',
            'catch_all_promote_threshold' => null,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\ncatch@example.com,catch_all\n");

        \App\Jobs\FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();

        $content = Storage::disk('s3')->get($job->risky_key);
        $lines = array_values(array_filter(explode("\n", trim($content))));
        $row = str_getcsv($lines[1] ?? '');

        $this->assertSame('catch@example.com', $row[0] ?? null);
        $this->assertSame('risky', $row[1] ?? null);
        $this->assertSame('catch_all', $row[2] ?? null);
    }

    public function test_catch_all_promotes_when_threshold_met(): void
    {
        Storage::fake('s3');

        config([
            'engine.catch_all_policy' => 'promote_if_score_gte',
            'engine.catch_all_promote_threshold' => 50,
        ]);

        EngineSetting::query()->update([
            'catch_all_policy' => 'promote_if_score_gte',
            'catch_all_promote_threshold' => 50,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeJob();

        $chunk = $this->makeChunk($job, 1, [
            'output_disk' => 's3',
            'valid_key' => $storage->chunkOutputKey($job, 1, 'valid'),
            'invalid_key' => $storage->chunkOutputKey($job, 1, 'invalid'),
            'risky_key' => $storage->chunkOutputKey($job, 1, 'risky'),
        ]);

        Storage::disk('s3')->put($chunk->valid_key, "email,reason\n");
        Storage::disk('s3')->put($chunk->invalid_key, "email,reason\n");
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\npromote@example.com,catch_all\n");

        \App\Jobs\FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();

        $content = Storage::disk('s3')->get($job->valid_key);
        $this->assertStringContainsString('promote@example.com,valid,catch_all', $content);
    }

    public function test_scores_are_clamped_and_cache_adjusted(): void
    {
        Storage::fake('s3');

        config([
            'engine.cache_store_driver' => 'database',
            'engine.cache_freshness_days' => 30,
            'engine.deliverability_score' => [
                'base' => ['valid' => 90, 'invalid' => 10, 'risky' => 55],
                'reason_overrides' => ['smtp_connect_ok' => 90, 'syntax' => 10],
                'sub_status_caps' => ['catch_all' => 80],
                'cache_adjustments' => ['valid' => 20, 'invalid' => -50, 'risky' => 0],
            ],
        ]);

        EmailVerificationOutcome::create([
            'email_hash' => hash('sha256', 'valid@example.com'),
            'email_normalized' => 'valid@example.com',
            'outcome' => 'valid',
            'observed_at' => now(),
        ]);

        EmailVerificationOutcome::create([
            'email_hash' => hash('sha256', 'invalid@example.com'),
            'email_normalized' => 'invalid@example.com',
            'outcome' => 'invalid',
            'observed_at' => now(),
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
        Storage::disk('s3')->put($chunk->risky_key, "email,reason\n");

        \App\Jobs\FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();

        $validContent = Storage::disk('s3')->get($job->valid_key);
        $invalidContent = Storage::disk('s3')->get($job->invalid_key);

        $this->assertStringContainsString('valid@example.com,valid,smtp_connect_ok,100', $validContent);
        $this->assertStringContainsString('invalid@example.com,invalid,syntax,0', $invalidContent);
    }

    public function test_legacy_email_reason_rows_merge_without_crashing(): void
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

        Storage::disk('s3')->put($chunk->valid_key, "legacy@example.com,smtp_connect_ok\n");
        Storage::disk('s3')->put($chunk->invalid_key, "legacy-invalid@example.com,syntax\n");
        Storage::disk('s3')->put($chunk->risky_key, "legacy-risky@example.com,smtp_timeout\n");

        \App\Jobs\FinalizeVerificationJob::dispatchSync($job->id);
        $job->refresh();

        $this->assertTrue(Storage::disk('s3')->exists($job->valid_key));
        $this->assertStringContainsString('legacy@example.com', Storage::disk('s3')->get($job->valid_key));
    }
}
