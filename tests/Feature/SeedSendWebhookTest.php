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
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $encoded, 'webhook-secret');

        $this->postJson(route('api.seed-send.webhook', ['provider' => 'log']), $payload, [
            'X-Seed-Signature' => $signature,
        ])
            ->assertStatus(202)
            ->assertJson([
                'message' => 'accepted',
            ]);

        Bus::assertDispatched(IngestSeedSendEventJob::class, function (IngestSeedSendEventJob $job): bool {
            return $job->provider === 'log'
                && ($job->payload['provider_message_id'] ?? null) === 'msg-1';
        });
    }
}
