<?php

namespace Tests\Feature;

use App\Services\QueueHealth\QueueHealthNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QueueHealthAlertingTest extends TestCase
{
    public function test_alert_cooldown_suppresses_duplicate_incident_notifications(): void
    {
        Cache::flush();
        Http::fake();

        config([
            'queue_health.alerts.enabled' => true,
            'queue_health.alerts.cooldown_seconds' => 600,
            'queue_health.alerts.email' => '',
            'queue_health.alerts.slack_webhook_url' => 'https://example.test/queue-health',
            'queue_health.alerts.cache_prefix' => 'queue-health-alert-test-cooldown',
        ]);

        $report = [
            'status' => 'warning',
            'checked_at' => now()->toIso8601String(),
            'issues' => [[
                'key' => 'lane_depth:redis:default',
                'severity' => 'warning',
                'title' => 'Queue depth high',
                'detail' => 'Lane default depth is 20 (threshold 10).',
                'lane' => 'default',
            ]],
        ];

        $notifier = app(QueueHealthNotifier::class);
        $notifier->notify($report);
        $notifier->notify($report);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Event: NEW'));
    }

    public function test_recovery_notification_is_sent_when_issue_clears(): void
    {
        Cache::flush();
        Http::fake();

        config([
            'queue_health.alerts.enabled' => true,
            'queue_health.alerts.cooldown_seconds' => 600,
            'queue_health.alerts.email' => '',
            'queue_health.alerts.slack_webhook_url' => 'https://example.test/queue-health',
            'queue_health.alerts.cache_prefix' => 'queue-health-alert-test-recovery',
        ]);

        $incidentReport = [
            'status' => 'critical',
            'checked_at' => now()->toIso8601String(),
            'issues' => [[
                'key' => 'horizon_inactive',
                'severity' => 'critical',
                'title' => 'Horizon inactive',
                'detail' => 'No Horizon master supervisors are running.',
                'lane' => null,
            ]],
        ];

        $recoveredReport = [
            'status' => 'healthy',
            'checked_at' => now()->addMinute()->toIso8601String(),
            'issues' => [],
        ];

        $notifier = app(QueueHealthNotifier::class);
        $notifier->notify($incidentReport);
        $notifier->notify($recoveredReport);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['text'], '[Queue Health][RECOVERED] Horizon inactive'));
    }
}
