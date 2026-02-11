<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedSendReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'seed_send.enabled' => true,
            'seed_send.reports.disk' => 'local',
        ]);
    }

    public function test_customer_can_download_sg6_evidence_report_for_own_job(): void
    {
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $campaign = $this->makeCampaign($job, $customer, 'results/seed-send/'.$customer->id.'/'.$job->id.'/campaign-report.csv');

        Storage::disk('local')->put($campaign->report_key, "email,status\nalpha@example.com,delivered\n");

        $this->actingAs($customer)
            ->get(route('portal.jobs.seed-send-report', [
                'job' => $job,
                'campaign_id' => $campaign->id,
            ]))
            ->assertOk()
            ->assertDownload();
    }

    public function test_customer_cannot_download_other_customer_sg6_report(): void
    {
        $owner = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();
        $job = $this->makeCompletedJob($owner);
        $campaign = $this->makeCampaign($job, $owner, 'results/seed-send/'.$owner->id.'/'.$job->id.'/campaign-report.csv');

        Storage::disk('local')->put($campaign->report_key, "email,status\nalpha@example.com,delivered\n");

        $this->actingAs($otherCustomer)
            ->get(route('portal.jobs.seed-send-report', [
                'job' => $job,
                'campaign_id' => $campaign->id,
            ]))
            ->assertForbidden();
    }

    private function makeCustomer(): User
    {
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $customer = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $customer->assignRole(Roles::CUSTOMER);

        return $customer;
    }

    private function makeCompletedJob(User $customer): VerificationJob
    {
        return VerificationJob::query()->create([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Completed,
            'verification_mode' => 'enhanced',
            'original_filename' => 'list.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$customer->id.'/completed/input.csv',
            'output_disk' => 'local',
        ]);
    }

    private function makeCampaign(VerificationJob $job, User $customer, string $reportKey): SeedSendCampaign
    {
        $consent = SeedSendConsent::query()->create([
            'verification_job_id' => $job->id,
            'user_id' => $customer->id,
            'scope' => 'full_list',
            'consent_text_version' => 'v1',
            'consent_text_snapshot' => 'SG6 consent text',
            'consented_at' => now(),
            'expires_at' => now()->addDays(30),
            'consented_by_user_id' => $customer->id,
            'status' => SeedSendConsent::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        return SeedSendCampaign::query()->create([
            'id' => (string) Str::uuid(),
            'verification_job_id' => $job->id,
            'user_id' => $customer->id,
            'seed_send_consent_id' => $consent->id,
            'status' => SeedSendCampaign::STATUS_COMPLETED,
            'target_scope' => 'full_list',
            'provider' => 'log',
            'target_count' => 1,
            'sent_count' => 1,
            'delivered_count' => 1,
            'report_disk' => 'local',
            'report_key' => $reportKey,
            'finished_at' => now(),
        ]);
    }
}
