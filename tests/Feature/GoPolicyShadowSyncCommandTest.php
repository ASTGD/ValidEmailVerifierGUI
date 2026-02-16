<?php

namespace Tests\Feature;

use App\Models\SmtpPolicyShadowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoPolicyShadowSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_shadow_runs_from_go_control_plane(): void
    {
        config()->set('services.go_control_plane.base_url', 'http://control-plane.test');
        config()->set('services.go_control_plane.token', 'test-token');
        config()->set('services.go_control_plane.timeout_seconds', 5);

        Http::fake([
            'http://control-plane.test/api/policies/shadow/runs*' => Http::response([
                'data' => [
                    [
                        'run_uuid' => 'run-uuid-1',
                        'candidate_version' => 'v3.2.0',
                        'active_version' => 'v3.1.9',
                        'triggered_by' => 'autopilot',
                        'evaluated_at' => now()->subMinute()->toIso8601String(),
                        'providers' => ['gmail'],
                        'summary' => [
                            'provider_count' => 1,
                            'unknown_rate_avg' => 0.2,
                            'tempfail_recovery_pct_avg' => 80.0,
                            'policy_blocked_rate_avg' => 0.04,
                            'highest_risk_recommendation' => 'set_cautious',
                        ],
                        'results' => [
                            [
                                'provider' => 'gmail',
                                'unknown_rate' => 0.2,
                                'tempfail_recovery_pct' => 80.0,
                                'policy_blocked_rate' => 0.04,
                                'recommendation' => 'set_cautious',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('ops:go-policy-shadow-sync --limit=10')
            ->assertSuccessful()
            ->expectsOutputToContain('Go shadow run sync complete');

        $this->assertDatabaseHas('smtp_policy_shadow_runs', [
            'run_uuid' => 'run-uuid-1',
            'candidate_version' => 'v3.2.0',
            'active_version' => 'v3.1.9',
            'provider' => 'gmail',
        ]);
    }

    public function test_command_dry_run_does_not_persist_rows(): void
    {
        config()->set('services.go_control_plane.base_url', 'http://control-plane.test');
        config()->set('services.go_control_plane.token', 'test-token');
        config()->set('services.go_control_plane.timeout_seconds', 5);

        Http::fake([
            'http://control-plane.test/api/policies/shadow/runs*' => Http::response([
                'data' => [
                    [
                        'run_uuid' => 'run-uuid-2',
                        'candidate_version' => 'v3.2.1',
                        'providers' => ['generic'],
                        'summary' => [
                            'provider_count' => 1,
                            'unknown_rate_avg' => 0.1,
                            'tempfail_recovery_pct_avg' => 90.0,
                            'policy_blocked_rate_avg' => 0.02,
                            'highest_risk_recommendation' => 'stable',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('ops:go-policy-shadow-sync --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry-run complete');

        $this->assertSame(0, SmtpPolicyShadowRun::query()->count());
    }
}
