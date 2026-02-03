<?php

namespace Tests\Feature;

use App\Models\EngineSetting;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Services\TempfailRetryPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TempfailRetryPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tempfail_retry_creates_retry_chunk_and_filters_risky_output(): void
    {
        Storage::fake('local');

        EngineSetting::query()->updateOrCreate([], [
            'tempfail_retry_enabled' => true,
            'tempfail_retry_max_attempts' => 2,
            'tempfail_retry_backoff_minutes' => '5',
            'tempfail_retry_reasons' => 'smtp_tempfail',
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => 'processing',
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $chunk = VerificationJobChunk::create([
            'verification_job_id' => $job->id,
            'chunk_no' => 1,
            'status' => 'completed',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
            'output_disk' => 'local',
            'risky_key' => 'results/chunks/'.$job->id.'/1/risky.csv',
            'email_count' => 2,
            'valid_count' => 0,
            'invalid_count' => 0,
            'risky_count' => 2,
            'retry_attempt' => 0,
        ]);

        Storage::disk('local')->put(
            $chunk->risky_key,
            "bad@example.com,smtp_tempfail\nok@example.com,role_account\n"
        );

        /** @var TempfailRetryPlanner $planner */
        $planner = app(TempfailRetryPlanner::class);
        $result = $planner->plan($job, $chunk, 'local');

        $this->assertSame(1, $result['retry_count']);

        $chunk->refresh();
        $this->assertSame(1, $chunk->risky_count);
        $this->assertNotSame('results/chunks/'.$job->id.'/1/risky.csv', $chunk->risky_key);

        $filtered = Storage::disk('local')->get($chunk->risky_key);
        $this->assertStringContainsString('ok@example.com', $filtered);
        $this->assertStringNotContainsString('bad@example.com', $filtered);

        $retryChunk = VerificationJobChunk::query()
            ->where('retry_parent_id', $chunk->id)
            ->first();

        $this->assertNotNull($retryChunk);
        $this->assertSame(1, $retryChunk->retry_attempt);
        $this->assertNotNull($retryChunk->available_at);

        $retryInput = Storage::disk('local')->get($retryChunk->input_key);
        $this->assertStringContainsString('bad@example.com', $retryInput);
    }
}
