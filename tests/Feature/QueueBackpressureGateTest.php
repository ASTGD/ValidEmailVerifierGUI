<?php

namespace Tests\Feature;

use App\Services\QueueHealth\QueueBackpressureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueBackpressureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_backpressure_blocks_heavy_submissions_on_critical_parse_issue(): void
    {
        config([
            'queue_slo.backpressure.enabled' => true,
            'queue_slo.backpressure.block_on_statuses' => ['critical'],
            'queue_slo.heavy_submission_lanes' => ['parse'],
            'queue_health.report_cache_key' => 'queue_health:test_backpressure',
        ]);

        Cache::put('queue_health:test_backpressure', [
            'status' => 'critical',
            'checked_at' => now()->toIso8601String(),
            'issues' => [[
                'key' => 'lane_oldest:redis_parse:parse',
                'severity' => 'critical',
                'title' => 'Oldest job age high',
                'detail' => 'Lane parse oldest job age is high.',
                'lane' => 'parse',
            ]],
        ], now()->addMinute());

        $gate = app(QueueBackpressureGate::class);
        $result = $gate->assessHeavySubmission();

        $this->assertTrue($result['blocked']);
        $this->assertStringContainsString('Oldest job age high', $result['reason']);
    }

    public function test_backpressure_allows_when_health_status_is_healthy(): void
    {
        config([
            'queue_slo.backpressure.enabled' => true,
            'queue_slo.backpressure.block_on_statuses' => ['critical'],
            'queue_slo.heavy_submission_lanes' => ['parse'],
            'queue_health.report_cache_key' => 'queue_health:test_backpressure_healthy',
        ]);

        Cache::put('queue_health:test_backpressure_healthy', [
            'status' => 'healthy',
            'checked_at' => now()->toIso8601String(),
            'issues' => [],
        ], now()->addMinute());

        $gate = app(QueueBackpressureGate::class);
        $result = $gate->assessHeavySubmission();

        $this->assertFalse($result['blocked']);
    }

    public function test_backpressure_blocks_when_report_is_stale(): void
    {
        config([
            'queue_slo.backpressure.enabled' => true,
            'queue_slo.backpressure.max_report_age_seconds' => 60,
            'queue_health.report_cache_key' => 'queue_health:test_backpressure_stale',
        ]);

        Cache::put('queue_health:test_backpressure_stale', [
            'status' => 'warning',
            'checked_at' => now()->subMinutes(10)->toIso8601String(),
            'issues' => [],
        ], now()->addMinute());

        $gate = app(QueueBackpressureGate::class);
        $result = $gate->assessHeavySubmission();

        $this->assertTrue($result['blocked']);
        $this->assertStringContainsString('stale', strtolower($result['reason']));
    }
}
