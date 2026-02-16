<?php

namespace Tests\Feature;

use App\Models\SmtpDecisionTrace;
use App\Models\SmtpPolicyShadowRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoPolicyRetentionPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_prune_dry_run_reports_go_policy_rows_without_deleting_them(): void
    {
        config()->set('engine.smtp_decision_trace_retention_days', 10);
        config()->set('engine.smtp_policy_shadow_run_retention_days', 20);

        SmtpDecisionTrace::query()->create([
            'verification_job_id' => null,
            'verification_job_chunk_id' => null,
            'email_hash' => hash('sha256', 'old-trace@example.com'),
            'provider' => 'gmail',
            'decision_class' => 'unknown',
            'confidence_hint' => 'low',
            'observed_at' => now()->subDays(30),
        ]);

        SmtpDecisionTrace::query()->create([
            'verification_job_id' => null,
            'verification_job_chunk_id' => null,
            'email_hash' => hash('sha256', 'fresh-trace@example.com'),
            'provider' => 'gmail',
            'decision_class' => 'unknown',
            'confidence_hint' => 'medium',
            'observed_at' => now()->subDays(2),
        ]);

        SmtpPolicyShadowRun::query()->create([
            'run_uuid' => '00000000-0000-0000-0000-000000000001',
            'candidate_version' => 'v4.0.0',
            'provider' => 'gmail',
            'status' => 'evaluated',
            'sample_size' => 100,
            'unknown_rate_delta' => 0.10,
            'tempfail_recovery_delta' => 0.05,
            'policy_block_rate_delta' => 0.02,
            'evaluated_at' => now()->subDays(45),
            'created_by' => 'tests',
        ]);

        SmtpPolicyShadowRun::query()->create([
            'run_uuid' => '00000000-0000-0000-0000-000000000002',
            'candidate_version' => 'v4.0.1',
            'provider' => 'gmail',
            'status' => 'evaluated',
            'sample_size' => 100,
            'unknown_rate_delta' => 0.08,
            'tempfail_recovery_delta' => 0.03,
            'policy_block_rate_delta' => 0.01,
            'evaluated_at' => now()->subDays(3),
            'created_by' => 'tests',
        ]);

        $this->artisan('ops:queue-prune --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('smtp_decision_traces=1');

        $this->assertSame(2, SmtpDecisionTrace::query()->count());
        $this->assertSame(2, SmtpPolicyShadowRun::query()->count());
    }

    public function test_queue_prune_deletes_expired_go_policy_rows(): void
    {
        config()->set('engine.smtp_decision_trace_retention_days', 10);
        config()->set('engine.smtp_policy_shadow_run_retention_days', 20);

        SmtpDecisionTrace::query()->create([
            'verification_job_id' => null,
            'verification_job_chunk_id' => null,
            'email_hash' => hash('sha256', 'expired-trace@example.com'),
            'provider' => 'generic',
            'decision_class' => 'unknown',
            'confidence_hint' => 'low',
            'observed_at' => now()->subDays(11),
        ]);

        SmtpDecisionTrace::query()->create([
            'verification_job_id' => null,
            'verification_job_chunk_id' => null,
            'email_hash' => hash('sha256', 'keep-trace@example.com'),
            'provider' => 'generic',
            'decision_class' => 'unknown',
            'confidence_hint' => 'medium',
            'observed_at' => now()->subDays(1),
        ]);

        SmtpPolicyShadowRun::query()->create([
            'run_uuid' => '00000000-0000-0000-0000-000000000010',
            'candidate_version' => 'v4.1.0',
            'provider' => 'generic',
            'status' => 'review_required',
            'sample_size' => 200,
            'unknown_rate_delta' => 0.14,
            'tempfail_recovery_delta' => 0.04,
            'policy_block_rate_delta' => 0.05,
            'evaluated_at' => now()->subDays(35),
            'created_by' => 'tests',
        ]);

        SmtpPolicyShadowRun::query()->create([
            'run_uuid' => '00000000-0000-0000-0000-000000000011',
            'candidate_version' => 'v4.1.1',
            'provider' => 'generic',
            'status' => 'evaluated',
            'sample_size' => 210,
            'unknown_rate_delta' => 0.09,
            'tempfail_recovery_delta' => 0.01,
            'policy_block_rate_delta' => 0.02,
            'evaluated_at' => now()->subDays(2),
            'created_by' => 'tests',
        ]);

        $this->artisan('ops:queue-prune')
            ->assertSuccessful()
            ->expectsOutputToContain('smtp_decision_traces=1');

        $this->assertSame(1, SmtpDecisionTrace::query()->count());
        $this->assertDatabaseHas('smtp_decision_traces', [
            'email_hash' => hash('sha256', 'keep-trace@example.com'),
        ]);

        $this->assertSame(1, SmtpPolicyShadowRun::query()->count());
        $this->assertDatabaseHas('smtp_policy_shadow_runs', [
            'run_uuid' => '00000000-0000-0000-0000-000000000011',
        ]);
    }
}
