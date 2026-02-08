<?php

namespace Tests\Feature;

use App\Models\QueueMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Tests\TestCase;

class QueueHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_health_command_reports_healthy_when_supervisors_and_lanes_are_ok(): void
    {
        $this->mockRedisOnline();
        $this->bindMasterSupervisors([
            [
                'name' => 'master-1',
                'status' => 'running',
                'supervisors' => ['host-1:supervisor-default'],
            ],
        ]);

        config([
            'queue_health.enabled' => true,
            'queue_health.alerts.enabled' => false,
            'queue_health.required_supervisors' => ['supervisor-default'],
            'queue_health.lanes' => [
                'default' => [
                    'driver' => 'redis',
                    'queue' => 'default',
                    'max_depth' => 100,
                    'max_oldest_age_seconds' => 300,
                ],
            ],
        ]);

        QueueMetric::create([
            'driver' => 'redis',
            'queue' => 'default',
            'depth' => 2,
            'failed_count' => 0,
            'oldest_age_seconds' => 10,
            'throughput_per_min' => 5,
            'captured_at' => now(),
        ]);

        $exitCode = Artisan::call('ops:queue-health --json');
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('healthy', $report['status']);
        $this->assertSame(0, data_get($report, 'summary.critical'));
        $this->assertSame(0, data_get($report, 'summary.warning'));
        $this->assertSame('healthy', data_get(Cache::get(config('queue_health.report_cache_key')), 'status'));
    }

    public function test_queue_health_command_returns_failure_when_required_supervisor_is_missing(): void
    {
        $this->mockRedisOnline();
        $this->bindMasterSupervisors([
            [
                'name' => 'master-1',
                'status' => 'running',
                'supervisors' => ['host-1:supervisor-default'],
            ],
        ]);

        config([
            'queue_health.enabled' => true,
            'queue_health.alerts.enabled' => false,
            'queue_health.required_supervisors' => ['supervisor-default', 'supervisor-parse'],
            'queue_health.lanes' => [],
        ]);

        $exitCode = Artisan::call('ops:queue-health --json');
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('critical', $report['status']);
        $issueKeys = collect($report['issues'] ?? [])->pluck('key')->all();

        $this->assertContains('missing_supervisor:supervisor-parse', $issueKeys);
    }

    public function test_queue_health_command_detects_lane_depth_and_age_threshold_breaches(): void
    {
        $this->mockRedisOnline();
        $this->bindMasterSupervisors([
            [
                'name' => 'master-1',
                'status' => 'running',
                'supervisors' => ['host-1:supervisor-default'],
            ],
        ]);

        config([
            'queue_health.enabled' => true,
            'queue_health.alerts.enabled' => false,
            'queue_health.required_supervisors' => ['supervisor-default'],
            'queue_health.lanes' => [
                'default' => [
                    'driver' => 'redis',
                    'queue' => 'default',
                    'max_depth' => 1,
                    'max_oldest_age_seconds' => 60,
                ],
            ],
        ]);

        QueueMetric::create([
            'driver' => 'redis',
            'queue' => 'default',
            'depth' => 5,
            'failed_count' => 1,
            'oldest_age_seconds' => 120,
            'throughput_per_min' => 1,
            'captured_at' => now(),
        ]);

        $exitCode = Artisan::call('ops:queue-health --json');
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('critical', $report['status']);

        $issueKeys = collect($report['issues'] ?? [])->pluck('key')->all();
        $this->assertContains('lane_depth:redis:default', $issueKeys);
        $this->assertContains('lane_oldest:redis:default', $issueKeys);
    }

    private function mockRedisOnline(): void
    {
        Redis::shouldReceive('connection')
            ->with('default')
            ->atLeast()
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->atLeast()
            ->once()
            ->andReturn('PONG');
    }

    /**
     * @param  array<int, array<string, mixed>>  $masters
     */
    private function bindMasterSupervisors(array $masters): void
    {
        $this->app->instance(MasterSupervisorRepository::class, new class($masters) implements MasterSupervisorRepository
        {
            /**
             * @param  array<int, array<string, mixed>>  $masters
             */
            public function __construct(private array $masters) {}

            public function names()
            {
                return [];
            }

            public function all()
            {
                return $this->masters;
            }

            public function find($name)
            {
                return [];
            }

            public function get(array $names)
            {
                return [];
            }

            public function update(MasterSupervisor $master)
            {
                // no-op for tests
            }

            public function forget($name)
            {
                // no-op for tests
            }

            public function flushExpired()
            {
                // no-op for tests
            }
        });
    }
}
