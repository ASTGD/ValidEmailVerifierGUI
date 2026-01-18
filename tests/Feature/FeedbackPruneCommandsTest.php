<?php

namespace Tests\Feature;

use App\Models\EmailVerificationOutcome;
use App\Models\EmailVerificationOutcomeImport;
use App\Support\EmailHashing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackPruneCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_email_outcomes_deletes_old_records(): void
    {
        config(['engine.feedback_retention_days' => 1]);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail('old@example.com'),
            'email_normalized' => 'old@example.com',
            'outcome' => 'invalid',
            'observed_at' => now()->subDays(3),
        ]);

        EmailVerificationOutcome::create([
            'email_hash' => EmailHashing::hashEmail('new@example.com'),
            'email_normalized' => 'new@example.com',
            'outcome' => 'valid',
            'observed_at' => now()->subHours(2),
        ]);

        Artisan::call('prune:email-outcomes');

        $this->assertDatabaseMissing('email_verification_outcomes', [
            'email_normalized' => 'old@example.com',
        ]);
        $this->assertDatabaseHas('email_verification_outcomes', [
            'email_normalized' => 'new@example.com',
        ]);
    }

    public function test_prune_feedback_imports_deletes_old_records_and_files(): void
    {
        Storage::fake('local');
        config(['engine.feedback_import_retention_days' => 1]);

        $fileKey = rtrim((string) config('engine.feedback_imports_prefix'), '/').'/old.csv';
        Storage::disk('local')->put($fileKey, 'email,outcome');

        $import = EmailVerificationOutcomeImport::create([
            'file_disk' => 'local',
            'file_key' => $fileKey,
            'source' => 'admin_import',
        ]);

        $import->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        Artisan::call('prune:feedback-imports');

        $this->assertDatabaseMissing('email_verification_outcome_imports', [
            'id' => $import->id,
        ]);
        Storage::disk('local')->assertMissing($fileKey);
    }
}
