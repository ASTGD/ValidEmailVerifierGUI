<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\QueueMetric;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoProbeWeeklyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_json_report(): void
    {
        QueueMetric::query()->create([
            'driver' => 'redis_smtp_probe',
            'queue' => 'smtp_probe',
            'depth' => 4,
            'failed_count' => 0,
            'oldest_age_seconds' => 60,
            'throughput_per_min' => 22,
            'captured_at' => now(),
        ]);

        $user = User::factory()->create();
        $job = VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Processing,
            'original_filename' => 'emails.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        VerificationJobMetric::query()->create([
            'verification_job_id' => (string) $job->id,
            'phase' => 'verify_chunks',
            'probe_completed_count' => 100,
            'probe_unknown_count' => 10,
            'phase_updated_at' => now(),
        ]);

        $this->artisan('ops:go-probe-weekly-report --json')
            ->assertSuccessful();
    }

    public function test_command_exports_report_to_storage(): void
    {
        Storage::fake('local');
        config()->set('queue_slo.weekly_report.disk', 'local');
        config()->set('queue_slo.weekly_report.prefix', 'reports/ops-test');

        $this->artisan('ops:go-probe-weekly-report')
            ->assertSuccessful()
            ->expectsOutputToContain('Weekly probe report exported');

        $files = Storage::disk('local')->allFiles('reports/ops-test');
        $this->assertNotEmpty($files);
    }
}
