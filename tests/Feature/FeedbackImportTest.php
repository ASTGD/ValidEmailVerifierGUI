<?php

namespace Tests\Feature;

use App\Jobs\ImportEmailVerificationOutcomesFromCsv;
use App\Models\EmailVerificationOutcome;
use App\Models\EmailVerificationOutcomeIngestion;
use App\Models\EmailVerificationOutcomeImport;
use App\Models\User;
use App\Support\EmailHashing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_csv_import_creates_outcomes(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $csv = implode("\n", [
            'email,outcome,reason_code,observed_at,source',
            'valid@example.com,valid,delivered,2026-01-01T00:00:00Z,customer_feedback',
            'bad-email,invalid,bounce_hard,2026-01-02T00:00:00Z,customer_feedback',
        ]);

        $fileKey = rtrim((string) config('engine.feedback_imports_prefix'), '/').'/sample.csv';
        Storage::disk('local')->put($fileKey, $csv);

        $import = EmailVerificationOutcomeImport::create([
            'user_id' => $user->id,
            'file_disk' => 'local',
            'file_key' => $fileKey,
            'source' => 'admin_import',
        ]);

        app()->call([new ImportEmailVerificationOutcomesFromCsv($import->id), 'handle']);

        $import->refresh();
        $this->assertSame(EmailVerificationOutcomeImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(1, $import->skipped_count);
        $this->assertNotEmpty($import->error_sample);

        $hash = EmailHashing::hashEmail('valid@example.com');
        $this->assertDatabaseHas('email_verification_outcomes', [
            'email_hash' => $hash,
            'outcome' => 'valid',
            'reason_code' => 'delivered',
            'source' => 'customer_feedback',
        ]);

        $this->assertSame(1, EmailVerificationOutcome::query()->count());
        $this->assertDatabaseHas('email_verification_outcome_ingestions', [
            'type' => 'import',
            'import_id' => $import->id,
            'item_count' => 2,
            'imported_count' => 1,
            'skipped_count' => 1,
        ]);
        $this->assertSame(1, EmailVerificationOutcomeIngestion::query()->count());
    }
}
