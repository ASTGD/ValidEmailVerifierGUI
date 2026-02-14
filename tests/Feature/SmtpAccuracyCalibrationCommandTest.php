<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\EmailVerificationOutcome;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\SeedSendRecipient;
use App\Models\SmtpConfidenceCalibration;
use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicySuggestion;
use App\Models\SmtpProbeQualityRollup;
use App\Models\SmtpTruthLabel;
use App\Models\User;
use App\Models\VerificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpAccuracyCalibrationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_truth_label_sync_persists_seed_send_recipient_labels(): void
    {
        $user = User::factory()->create();
        $job = VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'verification_mode' => 'enhanced',
            'original_filename' => 'input.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);
        $consent = SeedSendConsent::query()->create([
            'verification_job_id' => $job->id,
            'user_id' => $user->id,
            'status' => SeedSendConsent::STATUS_APPROVED,
            'scope' => 'full_list',
            'consented_at' => now()->subHour(),
            'approved_at' => now()->subMinutes(50),
        ]);
        $campaign = SeedSendCampaign::query()->create([
            'verification_job_id' => $job->id,
            'user_id' => $user->id,
            'seed_send_consent_id' => $consent->id,
            'status' => SeedSendCampaign::STATUS_COMPLETED,
            'provider' => 'gmail',
            'target_scope' => 'full_list',
            'target_count' => 1,
        ]);
        $recipient = SeedSendRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'deliverable@example.com',
            'email_hash' => hash('sha256', 'deliverable@example.com'),
            'status' => SeedSendRecipient::STATUS_DELIVERED,
            'attempt_count' => 1,
            'last_event_at' => now()->subMinutes(5),
            'provider_message_id' => 'msg-1',
        ]);

        $this->artisan('ops:smtp-truth-labels-sync --since-hours=24')
            ->assertSuccessful();

        $this->assertDatabaseHas(SmtpTruthLabel::class, [
            'source_recipient_id' => $recipient->id,
            'provider' => 'gmail',
            'truth_label' => 'deliverable',
            'decision_class' => 'deliverable',
            'reason_tag' => 'mailbox_exists',
        ]);
        $this->assertDatabaseHas(SmtpPolicyActionAudit::class, [
            'action' => 'truth_label_sync',
            'result' => 'success',
        ]);
    }

    public function test_confidence_calibration_rollup_matches_truth_labels_with_outcomes(): void
    {
        $hash = hash('sha256', 'calibration@example.com');
        SmtpTruthLabel::query()->create([
            'email_hash' => $hash,
            'provider' => 'gmail',
            'truth_label' => 'deliverable',
            'confidence_hint' => 'high',
            'source' => 'sg6_seed_send',
            'decision_class' => 'deliverable',
            'reason_tag' => 'mailbox_exists',
            'observed_at' => now(),
        ]);

        EmailVerificationOutcome::query()->create([
            'email_hash' => $hash,
            'email_normalized' => 'calibration@example.com',
            'outcome' => 'valid',
            'reason_code' => 'rcpt_ok',
            'details' => [
                'decision_confidence' => 'medium',
            ],
            'observed_at' => now()->subMinute(),
            'source' => 'smtp_probe',
        ]);

        $this->artisan('ops:smtp-confidence-calibrate --window-days=7')
            ->assertSuccessful();

        $this->assertDatabaseHas(SmtpConfidenceCalibration::class, [
            'provider' => 'gmail',
            'decision_class' => 'deliverable',
            'confidence_hint' => 'medium',
            'sample_count' => 1,
            'match_count' => 1,
            'unknown_count' => 0,
        ]);
        $this->assertDatabaseHas(SmtpPolicyActionAudit::class, [
            'action' => 'confidence_calibration',
            'result' => 'success',
        ]);
    }

    public function test_suggestion_generator_uses_precision_regression_signal(): void
    {
        config()->set('engine.smtp_ai_suggestion_enabled', true);
        config()->set('engine.smtp_ai_min_samples', 100);
        config()->set('engine.smtp_ai_unknown_rate_threshold', 0.20);
        config()->set('engine.smtp_ai_min_truth_samples', 50);
        config()->set('engine.smtp_ai_precision_floor', 0.85);

        SmtpProbeQualityRollup::query()->create([
            'rollup_date' => now()->toDateString(),
            'provider' => 'gmail',
            'sample_count' => 1000,
            'unknown_count' => 100,
            'tempfail_count' => 80,
            'policy_blocked_count' => 20,
            'retry_success_count' => 700,
            'unknown_rate' => 0.10,
            'tempfail_recovery_rate' => 0.70,
            'policy_blocked_rate' => 0.02,
            'retry_waste_rate' => 0.10,
        ]);

        SmtpConfidenceCalibration::query()->create([
            'rollup_date' => now()->toDateString(),
            'provider' => 'gmail',
            'decision_class' => 'deliverable',
            'confidence_hint' => 'high',
            'sample_count' => 200,
            'match_count' => 140,
            'unknown_count' => 30,
            'precision_rate' => 0.70,
            'supporting_metrics' => ['window_days' => 7],
        ]);

        $this->artisan('ops:smtp-policy-suggestions-generate --window-days=7')
            ->assertSuccessful();

        $this->assertDatabaseHas(SmtpPolicySuggestion::class, [
            'provider' => 'gmail',
            'suggestion_type' => 'confidence_regression',
            'status' => 'draft',
        ]);
    }
}
