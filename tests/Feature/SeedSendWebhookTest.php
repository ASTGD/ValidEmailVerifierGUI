<?php

namespace Tests\Feature;

use App\Jobs\IngestSeedSendEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SeedSendWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seed_send.enabled' => true,
            'seed_send.provider.default' => 'log',
            'seed_send.provider.providers.log.enabled' => true,
            'seed_send.provider.providers.log.webhook_secret' => 'webhook-secret',
            'seed_send.webhooks.signature_header' => 'X-Seed-Signature',
            'seed_send.webhooks.timestamp_header' => 'X-Seed-Timestamp',
            'seed_send.webhooks.nonce_header' => 'X-Seed-Nonce',
            'seed_send.webhooks.signature_max_age_seconds' => 300,
            'seed_send.webhooks.replay_cache_prefix' => 'seed_send:webhook:test',
        ]);
    }

    public function test_seed_send_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'campaign_id' => 'campaign-1',
            'provider_message_id' => 'msg-1',
            'event_type' => 'delivered',
        ];

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, [
            'X-Seed-Timestamp' => (string) now()->timestamp,
            'X-Seed-Nonce' => 'nonce-invalid',
            'X-Seed-Signature' => 'invalid-signature',
        ])->assertStatus(401);
    }

    public function test_seed_send_webhook_accepts_valid_signature_and_dispatches_ingest_job(): void
    {
        Bus::fake();

        $payload = [
            'campaign_id' => 'campaign-1',
            'provider_message_id' => 'msg-1',
            'event_type' => 'delivered',
            'event_time' => now()->toIso8601String(),
        ];
        $headers = $this->signedHeaders($payload, 'nonce-accepted');

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, $headers)
            ->assertStatus(202)
            ->assertJson([
                'message' => 'accepted',
            ]);

        Bus::assertDispatched(IngestSeedSendEventJob::class, function (IngestSeedSendEventJob $job): bool {
            return $job->provider === 'log'
                && ($job->payload['provider_message_id'] ?? null) === 'msg-1';
        });
    }

    public function test_seed_send_webhook_rejects_invalid_mapping_key(): void
    {
        Bus::fake();

        $payload = [
            'event_type' => 'delivered',
        ];
        $headers = $this->signedHeaders($payload, 'nonce-mapping-invalid');

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, $headers)
            ->assertStatus(422);

        Bus::assertNotDispatched(IngestSeedSendEventJob::class);
    }

    public function test_seed_send_webhook_rejects_replayed_nonce(): void
    {
        Bus::fake();

        $payload = [
            'campaign_id' => 'campaign-1',
            'email' => 'alpha@example.com',
            'event_type' => 'deferred',
        ];
        $headers = $this->signedHeaders($payload, 'nonce-replay-1');

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, $headers)
            ->assertStatus(202);

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, $headers)
            ->assertStatus(401);
    }

    public function test_seed_send_webhook_accepts_batched_events_and_dispatches_each_event(): void
    {
        Bus::fake();

        $payload = [
            [
                'campaign_id' => 'campaign-1',
                'provider_message_id' => 'msg-1',
                'event_type' => 'delivered',
            ],
            [
                'campaign_id' => 'campaign-1',
                'provider_message_id' => 'msg-2',
                'event_type' => 'bounced',
            ],
        ];

        $headers = $this->signedHeaders($payload, 'nonce-batch-1');

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, $headers)
            ->assertStatus(202)
            ->assertJson([
                'message' => 'accepted',
                'accepted_events' => 2,
            ]);

        Bus::assertDispatchedTimes(IngestSeedSendEventJob::class, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signedHeaders(array $payload, string $nonce): array
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', sprintf("%s\n%s\n%s", $timestamp, $nonce, $encoded), 'webhook-secret');

        return [
            'X-Seed-Timestamp' => $timestamp,
            'X-Seed-Nonce' => $nonce,
            'X-Seed-Signature' => $signature,
        ];
    }
}
