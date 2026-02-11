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
use App\Services\SeedSend\SeedSendCreditLedgerService;
use App\Services\SeedSend\SeedSendEventIngestor;
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

        $this->actingAs($customer)
            ->get(route('internal.admin.seed-send.health'))
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

    public function test_admin_can_revoke_approved_consent(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.consents.revoke', $consent), [
                'reason' => 'customer request',
            ])
            ->assertRedirect();

        $consent->refresh();
        $this->assertSame(SeedSendConsent::STATUS_REVOKED, $consent->status);
        $this->assertNotNull($consent->revoked_at);
    }

    public function test_revoking_consent_stops_active_campaign_and_blocks_future_dispatch(): void
    {
        config([
            'seed_send.webhooks.required' => false,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, ['alpha@example.com', 'beta@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), [
                'consent_id' => $consent->id,
            ])
            ->assertRedirect();

        $campaign = SeedSendCampaign::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.consents.revoke', $consent), [
                'reason' => 'pilot revoked',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(SeedSendCampaign::STATUS_CANCELLED, $campaign->status);
        $this->assertSame('consent_revoked', $campaign->failure_reason);

        $this->assertSame(0, SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [
                SeedSendRecipient::STATUS_PENDING,
                SeedSendRecipient::STATUS_DISPATCHING,
                SeedSendRecipient::STATUS_DISPATCHED,
            ])
            ->count());

        app()->call([new DispatchSeedSendCampaignJob($campaign->id), 'handle']);

        $campaign->refresh();
        $this->assertSame(SeedSendCampaign::STATUS_CANCELLED, $campaign->status);
        $this->assertSame(0, SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', SeedSendRecipient::STATUS_DISPATCHED)
            ->count());
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

    public function test_seed_send_campaign_start_blocks_expired_consent(): void
    {
        config([
            'seed_send.consent.expiry_days' => 1,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED, [
            'expires_at' => now()->subMinute(),
        ]);
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
        $this->assertNotNull($campaign->report_key);
        $this->assertNotNull($campaign->report_disk);
        $this->assertTrue(Storage::disk((string) $campaign->report_disk)->exists((string) $campaign->report_key));

        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_RESERVE,
            'credits' => 6,
        ]);
        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_CONSUME,
            'credits' => 6,
        ]);
        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_RELEASE,
            'credits' => 0,
        ]);
    }

    public function test_admin_can_cancel_campaign_and_release_reserved_credits(): void
    {
        config([
            'seed_send.credits.per_recipient' => 2,
            'seed_send.webhooks.required' => false,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, ['alpha@example.com', 'beta@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), ['consent_id' => $consent->id])
            ->assertRedirect();

        $campaign = SeedSendCampaign::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.cancel', $campaign), [
                'reason' => 'pilot stop',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(SeedSendCampaign::STATUS_CANCELLED, $campaign->status);

        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_RELEASE,
            'credits' => 4,
        ]);
    }

    public function test_cancellation_after_partial_progress_settles_consume_and_release_credits(): void
    {
        config([
            'seed_send.credits.per_recipient' => 2,
            'seed_send.webhooks.required' => false,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, ['alpha@example.com', 'beta@example.com', 'gamma@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), ['consent_id' => $consent->id])
            ->assertRedirect();

        $campaign = SeedSendCampaign::query()->firstOrFail();

        $firstRecipient = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->firstOrFail();

        $firstRecipient->update([
            'status' => SeedSendRecipient::STATUS_DELIVERED,
            'attempt_count' => 1,
            'last_attempt_at' => now(),
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.cancel', $campaign), [
                'reason' => 'partial settlement check',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(SeedSendCampaign::STATUS_CANCELLED, $campaign->status);

        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_CONSUME,
            'credits' => 2,
        ]);
        $this->assertDatabaseHas('seed_send_credit_ledger', [
            'campaign_id' => $campaign->id,
            'entry_type' => SeedSendCreditLedgerService::ENTRY_RELEASE,
            'credits' => 4,
        ]);
    }

    public function test_admin_retry_failed_subset_requeues_deferred_and_failed_recipients(): void
    {
        config([
            'seed_send.webhooks.required' => false,
        ]);

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $job = $this->makeCompletedJob($customer);
        $consent = $this->makeConsent($job, $customer, SeedSendConsent::STATUS_APPROVED);
        $this->storeResultFiles($job, ['alpha@example.com', 'beta@example.com']);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.start', $job), ['consent_id' => $consent->id])
            ->assertRedirect();

        $campaign = SeedSendCampaign::query()->firstOrFail();

        SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->limit(1)
            ->update(['status' => SeedSendRecipient::STATUS_FAILED]);

        SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('id')
            ->limit(1)
            ->update(['status' => SeedSendRecipient::STATUS_DEFERRED]);

        $this->actingAs($admin)
            ->post(route('internal.admin.seed-send.campaigns.retry-failed', $campaign), [
                'max_recipients' => 10,
            ])
            ->assertRedirect();

        $pendingCount = SeedSendRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', SeedSendRecipient::STATUS_PENDING)
            ->count();

        $this->assertGreaterThanOrEqual(2, $pendingCount);
    }

    public function test_seed_send_event_ingestor_is_idempotent_by_dedupe_key(): void
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

        $recipient = SeedSendRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'email' => 'alpha@example.com',
            'email_hash' => hash('sha256', 'alpha@example.com'),
            'status' => SeedSendRecipient::STATUS_DISPATCHED,
            'provider_message_id' => 'msg-dedupe-1',
        ]);

        $payload = [
            'provider_message_id' => 'msg-dedupe-1',
            'event_type' => 'delivered',
            'event_time' => now()->toIso8601String(),
        ];

        /** @var SeedSendEventIngestor $ingestor */
        $ingestor = app(SeedSendEventIngestor::class);
        $ingestor->ingest('log', $payload);
        $ingestor->ingest('log', $payload);

        $this->assertDatabaseCount('seed_send_events', 1);

        $recipient->refresh();
        $campaign->refresh();

        $this->assertSame(SeedSendRecipient::STATUS_DELIVERED, $recipient->status);
        $this->assertSame(1, $campaign->delivered_count);
    }

    public function test_admin_health_endpoint_returns_summary_payload(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('internal.admin.seed-send.health'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checked_at',
                'active_campaigns',
                'running_campaigns',
                'queued_campaigns',
                'paused_campaigns',
                'provider',
                'issues',
            ]);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeConsent(VerificationJob $job, User $customer, string $status, array $overrides = []): SeedSendConsent
    {
        return SeedSendConsent::query()->create(array_merge([
            'verification_job_id' => $job->id,
            'user_id' => $customer->id,
            'scope' => 'full_list',
            'consent_text_version' => 'v1',
            'consent_text_snapshot' => 'SG6 consent text',
            'consented_at' => now(),
            'expires_at' => now()->addDays(30),
            'consented_by_user_id' => $customer->id,
            'status' => $status,
            'approved_at' => $status === SeedSendConsent::STATUS_APPROVED ? now() : null,
            'approved_by_admin_id' => null,
        ], $overrides));
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
