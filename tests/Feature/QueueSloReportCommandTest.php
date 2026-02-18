<?php

namespace Tests\Feature;

use App\Models\QueueIncident;
use App\Models\QueueMetric;
use App\Models\QueueMetricsRollup;
use App\Services\QueueHealth\QueueHealthEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class QueueSloReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_slo_report_outputs_lane_and_incident_data(): void
    {
        $this->mock(QueueHealthEvaluator::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->once()->andReturn([
                'status' => 'healthy',
                'issues' => [],
            ]);
        });

        config([
            'queue_health.lanes' => [
                'default' => [
                    'driver' => 'redis',
                    'queue' => 'default',
                    'max_depth' => 100,
                    'max_oldest_age_seconds' => 300,
                ],
            ],
            'queue_slo.retry_contracts' => [
                'redis' => [
                    'timeout' => 90,
                    'retry_after' => 180,
                ],
            ],
            'queue.connections.redis.retry_after' => 180,
        ]);

        QueueMetric::create([
            'driver' => 'redis',
            'queue' => 'default',
            'depth' => 2,
            'failed_count' => 0,
            'oldest_age_seconds' => 10,
            'throughput_per_min' => 4,
            'captured_at' => now(),
        ]);

        QueueMetricsRollup::create([
            'driver' => 'redis',
            'queue' => 'default',
            'period_type' => 'hour',
            'period_start' => now()->startOfHour(),
            'samples' => 5,
            'avg_depth' => 1.2,
            'max_depth' => 3,
            'avg_oldest_age_seconds' => 20,
            'max_oldest_age_seconds' => 40,
            'avg_failed_count' => 0,
            'max_failed_count' => 0,
            'avg_throughput_per_min' => 3,
            'max_throughput_per_min' => 5,
        ]);

        QueueIncident::create([
            'issue_key' => 'lane_depth:redis:default',
            'severity' => 'warning',
            'status' => 'detected',
            'lane' => 'default',
            'title' => 'Queue depth high',
            'detail' => 'Depth exceeded threshold.',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $exitCode = Artisan::call('ops:queue-slo-report --json');
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('healthy', $report['health_status']);
        $this->assertSame(1, $report['open_incidents']);
        $this->assertCount(1, $report['lanes']);
        $this->assertSame('default', $report['lanes'][0]['lane']);
    }

    public function test_queue_slo_report_fails_when_retry_contract_is_unsafe(): void
    {
        $this->mock(QueueHealthEvaluator::class, function ($mock): void {
            $mock->shouldReceive('evaluate')->once()->andReturn([
                'status' => 'healthy',
                'issues' => [],
            ]);
        });

        config([
            'queue_health.lanes' => [],
            'queue_slo.retry_safety_buffer_seconds' => 30,
            'queue_slo.retry_contracts' => [
                'redis_prepare' => [
                    'timeout' => 200,
                    'retry_after' => 200,
                ],
            ],
            'queue.connections.redis_prepare.retry_after' => 200,
        ]);

        $exitCode = Artisan::call('ops:queue-slo-report --json');
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertFalse($report['retry_contracts'][0]['safe']);
    }
}
