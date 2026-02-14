<?php

namespace Tests\Feature;

use App\Models\SmtpPolicySuggestion;
use App\Models\SmtpProbeQualityRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpPolicySuggestionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_draft_suggestion_from_probe_rollups(): void
    {
        config()->set('engine.smtp_ai_suggestion_enabled', true);
        config()->set('engine.smtp_ai_min_samples', 100);
        config()->set('engine.smtp_ai_unknown_rate_threshold', 0.20);

        SmtpProbeQualityRollup::query()->create([
            'rollup_date' => now()->toDateString(),
            'provider' => 'gmail',
            'sample_count' => 500,
            'unknown_count' => 150,
            'tempfail_count' => 120,
            'policy_blocked_count' => 30,
            'retry_success_count' => 90,
            'unknown_rate' => 0.30,
            'tempfail_recovery_rate' => 0.40,
            'policy_blocked_rate' => 0.06,
            'retry_waste_rate' => 0.12,
        ]);

        $this->artisan('ops:smtp-policy-suggestions-generate --window-days=7')
            ->assertSuccessful()
            ->expectsOutputToContain('Generated 1 SMTP policy suggestion draft(s).');

        $this->assertDatabaseHas(SmtpPolicySuggestion::class, [
            'provider' => 'gmail',
            'status' => 'draft',
            'suggestion_type' => 'unknown_rate_regression',
        ]);
    }
}
