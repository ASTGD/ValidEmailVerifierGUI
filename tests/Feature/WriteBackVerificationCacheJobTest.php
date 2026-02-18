<?php

namespace Tests\Feature;

use App\Contracts\CacheWriteBackService;
use App\Enums\VerificationJobStatus;
use App\Jobs\WriteBackVerificationCacheJob;
use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WriteBackVerificationCacheJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompletedJob(): VerificationJob
    {
        $user = User::factory()->create();

        return VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'original_filename' => 'emails.csv',
            'input_disk' => 's3',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
            'output_disk' => 's3',
        ]);
    }

    public function test_writeback_job_records_completed_status_on_success(): void
    {
        Storage::fake('s3');

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeCompletedJob();
        $job->update([
            'cache_miss_key' => $storage->cacheMissKey($job),
            'valid_key' => $storage->finalResultKey($job, 'valid'),
            'invalid_key' => $storage->finalResultKey($job, 'invalid'),
            'risky_key' => $storage->finalResultKey($job, 'risky'),
        ]);

        $this->mock(CacheWriteBackService::class, function ($mock): void {
            $mock->shouldReceive('writeBack')
                ->once()
                ->andReturn([
                    'status' => 'completed',
                    'attempted' => 5,
                    'written' => 4,
                ]);
        });

        app()->call([new WriteBackVerificationCacheJob($job->id), 'handle']);

        $job->refresh();
        $metrics = $job->metrics()->first();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertNotNull($metrics);
        $this->assertSame('completed', $metrics?->writeback_status);
        $this->assertSame(5, $metrics?->writeback_attempted_count);
        $this->assertSame(4, $metrics?->writeback_written_count);
        $this->assertNull($metrics?->writeback_last_error);
        $this->assertNotNull($metrics?->writeback_started_at);
        $this->assertNotNull($metrics?->writeback_finished_at);
    }

    public function test_writeback_job_records_failure_without_changing_verification_status(): void
    {
        Storage::fake('s3');

        EngineSetting::query()->update([
            'cache_writeback_enabled' => true,
        ]);

        $storage = app(JobStorage::class);
        $job = $this->makeCompletedJob();
        $job->update([
            'cache_miss_key' => $storage->cacheMissKey($job),
            'valid_key' => $storage->finalResultKey($job, 'valid'),
        ]);

        $this->mock(CacheWriteBackService::class, function ($mock): void {
            $mock->shouldReceive('writeBack')
                ->once()
                ->andThrow(new RuntimeException('simulated write-back failure'));
        });

        try {
            app()->call([new WriteBackVerificationCacheJob($job->id), 'handle']);
            $this->fail('Expected write-back job to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated write-back failure', $exception->getMessage());
        }

        $job->refresh();
        $metrics = $job->metrics()->first();

        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertNotNull($metrics);
        $this->assertSame('failed', $metrics?->writeback_status);
        $this->assertSame('simulated write-back failure', $metrics?->writeback_last_error);
        $this->assertNotNull($metrics?->writeback_started_at);
        $this->assertNotNull($metrics?->writeback_finished_at);
    }

    public function test_writeback_job_skips_when_job_is_not_completed(): void
    {
        $user = User::factory()->create();

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $this->mock(CacheWriteBackService::class, function ($mock): void {
            $mock->shouldReceive('writeBack')->never();
        });

        app()->call([new WriteBackVerificationCacheJob($job->id), 'handle']);

        $job->refresh();

        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertNull($job->metrics()->first());
    }
}
