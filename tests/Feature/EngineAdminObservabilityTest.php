<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Filament\Resources\VerificationJobs\Pages\ViewVerificationJob;
use App\Filament\Widgets\FinalizationHealth;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobChunk;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EngineAdminObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function widgetStats(): array
    {
        $widget = app(FinalizationHealth::class);
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));
        $admin->assignRole(Roles::ADMIN);

        return $admin;
    }

    private function makeJob(array $overrides = []): VerificationJob
    {
        $user = User::factory()->create();

        return VerificationJob::create(array_merge([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ], $overrides));
    }

    public function test_health_widget_detects_completed_missing_outputs(): void
    {
        $this->makeJob([
            'status' => VerificationJobStatus::Completed,
            'valid_key' => null,
            'invalid_key' => null,
            'risky_key' => null,
        ]);

        $stats = $this->widgetStats();
        $missingOutputs = $stats[1]->getValue() ?? 0;

        $this->assertSame(1, $missingOutputs);
    }

    public function test_health_widget_detects_failed_chunks_and_stuck_chunks(): void
    {
        $job = $this->makeJob();

        VerificationJobChunk::create([
            'verification_job_id' => $job->id,
            'chunk_no' => 1,
            'status' => 'failed',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
        ]);

        VerificationJobChunk::create([
            'verification_job_id' => $job->id,
            'chunk_no' => 2,
            'status' => 'processing',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/2/input.txt',
            'claim_expires_at' => now()->subMinutes(5),
        ]);

        $stats = $this->widgetStats();

        $failedChunks = $stats[0]->getValue() ?? 0;
        $stuckChunks = $stats[3]->getValue() ?? 0;

        $this->assertSame(1, $failedChunks);
        $this->assertSame(1, $stuckChunks);
    }

    public function test_requeue_failed_chunks_action_logs_job_event(): void
    {
        $admin = $this->makeAdmin();
        $job = $this->makeJob();

        $chunk = VerificationJobChunk::create([
            'verification_job_id' => $job->id,
            'chunk_no' => 1,
            'status' => 'failed',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
            'attempts' => 1,
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewVerificationJob::class, ['record' => $job->id])
            ->callAction('requeue_failed_chunks');

        $chunk->refresh();
        $this->assertSame('pending', $chunk->status);
        $this->assertSame(2, $chunk->attempts);
        $this->assertDatabaseHas('verification_job_logs', [
            'verification_job_id' => $job->id,
            'event' => 'chunks_requeued',
        ]);
    }

    public function test_requeue_stuck_chunks_action_logs_job_event(): void
    {
        $admin = $this->makeAdmin();
        $job = $this->makeJob();

        $chunk = VerificationJobChunk::create([
            'verification_job_id' => $job->id,
            'chunk_no' => 1,
            'status' => 'processing',
            'input_disk' => 'local',
            'input_key' => 'chunks/'.$job->id.'/1/input.txt',
            'attempts' => 1,
            'claim_expires_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewVerificationJob::class, ['record' => $job->id])
            ->callAction('requeue_stuck_chunks');

        $chunk->refresh();
        $this->assertSame('pending', $chunk->status);
        $this->assertSame(2, $chunk->attempts);
        $this->assertDatabaseHas('verification_job_logs', [
            'verification_job_id' => $job->id,
            'event' => 'chunks_requeued',
        ]);
    }
}
