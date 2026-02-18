<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\SeedSendConsent;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedSendConsentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seed_send.enabled' => true,
            'seed_send.webhooks.required' => false,
            'seed_send.consent.text' => 'Pilot SG6 consent text',
            'seed_send.consent.expiry_days' => 14,
        ]);
    }

    public function test_customer_can_request_seed_send_consent_for_completed_job(): void
    {
        $customer = $this->makeCustomer();
        $job = $this->makeJob($customer, VerificationJobStatus::Completed);

        $this->actingAs($customer)
            ->post(route('portal.jobs.seed-send-consent', $job), [
                'scope' => 'full_list',
            ])
            ->assertRedirect();

        $consent = SeedSendConsent::query()->first();
        $this->assertNotNull($consent);
        $this->assertSame($job->id, $consent->verification_job_id);
        $this->assertSame($customer->id, $consent->user_id);
        $this->assertSame(SeedSendConsent::STATUS_REQUESTED, $consent->status);
        $this->assertSame('full_list', $consent->scope);
        $this->assertSame('Pilot SG6 consent text', $consent->consent_text_snapshot);
        $this->assertNotNull($consent->expires_at);
    }

    public function test_seed_send_consent_request_requires_completed_job(): void
    {
        $customer = $this->makeCustomer();
        $job = $this->makeJob($customer, VerificationJobStatus::Processing);

        $this->actingAs($customer)
            ->post(route('portal.jobs.seed-send-consent', $job))
            ->assertForbidden();

        $this->assertDatabaseCount('seed_send_consents', 0);
    }

    public function test_seed_send_consent_request_returns_not_found_when_feature_disabled(): void
    {
        config(['seed_send.enabled' => false]);

        $customer = $this->makeCustomer();
        $job = $this->makeJob($customer, VerificationJobStatus::Completed);

        $this->actingAs($customer)
            ->post(route('portal.jobs.seed-send-consent', $job))
            ->assertNotFound();
    }

    public function test_portal_job_page_hides_seed_send_section_when_feature_disabled(): void
    {
        config(['seed_send.enabled' => false]);

        $customer = $this->makeCustomer();
        $job = $this->makeJob($customer, VerificationJobStatus::Completed);

        $this->actingAs($customer)
            ->get(route('portal.jobs.show', $job))
            ->assertOk()
            ->assertDontSee('SG6 Seed-Send Verification');
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

    private function makeJob(User $user, VerificationJobStatus $status): VerificationJob
    {
        return VerificationJob::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'verification_mode' => 'enhanced',
            'original_filename' => 'list.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);
    }
}
