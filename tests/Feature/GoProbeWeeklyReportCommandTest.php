<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\QueueMetric;
use App\Models\User;
use App\Models\VerificationJob;
use App\Models\VerificationJobMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    public function test_command_reads_finalize_and_probe_queue_names_from_config_aliases(): void
    {
        config()->set('queue.connections.redis_finalize.queue', 'finalize_alias');
        config()->set('queue.connections.redis_smtp_probe.queue', 'smtp_probe_alias');

        QueueMetric::query()->create([
            'driver' => 'redis_finalize',
            'queue' => 'finalize_alias',
            'depth' => 6,
            'failed_count' => 0,
            'oldest_age_seconds' => 45,
            'throughput_per_min' => 16,
            'captured_at' => now(),
        ]);

        QueueMetric::query()->create([
            'driver' => 'redis_smtp_probe',
            'queue' => 'smtp_probe_alias',
            'depth' => 9,
            'failed_count' => 0,
            'oldest_age_seconds' => 120,
            'throughput_per_min' => 12,
            'captured_at' => now(),
        ]);

        Artisan::call('ops:go-probe-weekly-report', ['--json' => true]);
        $output = Artisan::output();

        $report = json_decode($output, true);
        $this->assertIsArray($report);
        $this->assertGreaterThan(0, data_get($report, 'queues.finalize.avg_depth', 0));
        $this->assertGreaterThan(0, data_get($report, 'queues.smtp_probe.avg_depth', 0));
    }
}
