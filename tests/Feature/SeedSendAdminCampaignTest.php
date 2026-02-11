<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Jobs\DispatchSeedSendCampaignJob;
use App\Jobs\ReconcileSeedSendCampaignJob;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendConsent;
use App\Models\SeedSendRecipient;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedSendAdminCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'seed_send.enabled' => true,
            'seed_send.provider.default' => 'log',
            'seed_send.provider.providers.log.enabled' => true,
            'seed_send.provider.providers.log.webhook_secret' => 'test-secret',
            'seed_send.webhooks.required' => true,
            'seed_send.credits.per_recipient' => 1,
            'queue.connections.redis_seed_send_dispatch.queue' => 'seed_send_dispatch',
            'queue.connections.redis_seed_send_events.queue' => 'seed_send_events',
            'queue.connections.redis_seed_send_reconcile.queue' => 'seed_send_reconcile',
        ]);
    }

    public function test_admin_only_endpoints_are_enforced_for_campaign_start_and_pause(): void
    {
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $campaign = SeedSendCampaign::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'verification_job_id' => $job->id,
            'user_id' => $customer->id,
            'seed_send_consent_id' => $consent->id,
            'status' => SeedSendCampaign::STATUS_RUNNING,
            'target_scope' => 'full_list',
            'provider' => 'log',
            'target_count' => 1,
        ]);

        $this->actingAs($customer)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), [
                'consent_id' => $consent->id,
            ])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('internal.admin.seed-send.campaigns.state', $campaign), [
                'action' => 'pause',
            ])
            ->assertForbidden();
    }

    public function test_seed_send_campaign_start_requires_approved_consent(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_REQUESTED);
        $this->storeResultFiles($job, ['alpha@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), [
                'consent_id' => $consent->id,
            ])
            ->assertSessionHasErrors('seed_send');

        $this->assertDatabaseCount('seed_send_campaigns', 0);
    }

    public function test_seed_send_campaign_start_requires_completed_job(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = VerificationJob::query()->create([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Processing,
            'verification_mode' => 'enhanced',
            'original_filename' => 'list.csv',
            'input_disk' => 'local',
            'input_key' => 'uploads/'.$customer->id.'/processing/input.csv',
        ]);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, ['alpha@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), [
                'consent_id' => $consent->id,
            ])
            ->assertSessionHasErrors('seed_send');

        $this->assertDatabaseCount('seed_send_campaigns', 0);
    }

    public function test_credit_reservation_and_settlement_are_recorded_for_campaign(): void
    {
        config([
            'seed_send.credits.per_recipient' => 2,
            'seed_send.webhooks.required' => false,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, [
            'alpha@example.com',
            'beta@example.com',
            'gamma@example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), [
                'consent_id' => $consent->id,
            ])
            ->assertRedirect();

        $campaign = SeedSendCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame(3, $campaign->target_count);
        $this->assertSame(6, $campaign->credits_reserved);
        $this->assertSame(SeedSendCampaign::STATUS_QUEUED, $campaign->status);

        app()->call([new DispatchSeedSendCampaignJob($campaign->id), 'handle']);

        SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->update([
                'status' => SeedSendRecipient::STATUS_DELIVERED,
                'last_event_at' => now(),
            ]);

        $campaign->refresh();
        $campaign->update([
            'status' => SeedSendCampaign::STATUS_RUNNING,
            'started_at' => now()->subHours(2),
        ]);

        app()->call([new ReconcileSeedSendCampaignJob($campaign->id), 'handle']);

        $campaign->refresh();
        $this->assertSame(SeedSendCampaign::STATUS_COMPLETED, $campaign->status);
        $this->assertSame(3, $campaign->delivered_count);
        $this->assertSame(6, $campaign->credits_used);
    }

    private function makeAdmin(): User
    {
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));
        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Roles::ADMIN);

        return $admin;
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
            'valid_key' => 'results/'.$customer->id.'/job-valid.csv',
            'invalid_key' => 'results/'.$customer->id.'/job-invalid.csv',
            'risky_key' => 'results/'.$customer->id.'/job-risky.csv',
        ]);
    }

    private function makeConsent(VerificationJob $job, User $customer, string $status): SeedSendConsent
    {
        return SeedSendConsent::query()->create([
            'verification_job_id' => $job->id,
            'user_id' => $customer->id,
            'scope' => 'full_list',
            'consent_text_version' => 'v1',
            'consented_at' => now(),
            'consented_by_user_id' => $customer->id,
            'status' => $status,
            'approved_at' => $status === SeedSendConsent::STATUS_APPROVED ? now() : null,
            'approved_by_admin_id' => null,
        ]);
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function storeResultFiles(VerificationJob $job, array $emails): void
    {
        if (! $job->valid_key || ! $job->invalid_key || ! $job->risky_key) {
            $job->update([
                'output_disk' => 'local',
                'valid_key' => $job->valid_key ?: 'results/'.$job->user_id.'/'.$job->id.'/valid.csv',
                'invalid_key' => $job->invalid_key ?: 'results/'.$job->user_id.'/'.$job->id.'/invalid.csv',
                'risky_key' => $job->risky_key ?: 'results/'.$job->user_id.'/'.$job->id.'/risky.csv',
            ]);
            $job->refresh();
        }

        $header = "email,status,sub_status,score,reason\n";
        $rows = collect($emails)
            ->map(fn (string $email): string => sprintf('%s,valid,valid,100,seed_test', $email))
            ->implode("\n");

        Storage::disk('local')->put($job->valid_key, $header.$rows."\n");
        Storage::disk('local')->put($job->invalid_key, "email,reason\n");
        Storage::disk('local')->put($job->risky_key, "email,reason\n");
    }
}
