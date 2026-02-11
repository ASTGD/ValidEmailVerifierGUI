<?php

namespace App\Services\SeedSend\Providers;

use App\Contracts\SeedSendProvider;
use App\Models\SeedSendCampaign;
use App\Models\SeedSendRecipient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogSeedSendProvider implements SeedSendProvider
{
    public function dispatch(SeedSendCampaign $campaign, SeedSendRecipient $recipient): array
    {
        $messageId = sprintf('seed-%s', (string) Str::uuid());
        $payload = [
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'email' => $recipient->email,
            'provider' => $campaign->provider,
            'message_id' => $messageId,
        ];

        Log::info('Seed send dispatch (log provider).', $payload);

        return [
            'provider_message_id' => $messageId,
            'payload' => $payload,
        ];
    }

    public function normalizeWebhookEvents(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $events = array_is_list($payload) ? $payload : [$payload];
        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $normalized[] = [
                'campaign_id' => trim((string) ($event['campaign_id'] ?? '')),
                'email' => strtolower(trim((string) ($event['email'] ?? ''))),
                'provider_message_id' => trim((string) ($event['provider_message_id'] ?? $event['message_id'] ?? '')),
                'event_type' => trim((string) ($event['event_type'] ?? $event['event'] ?? '')),
                'event_time' => $event['event_time'] ?? $event['timestamp'] ?? null,
                'smtp_code' => trim((string) ($event['smtp_code'] ?? '')),
                'enhanced_code' => trim((string) ($event['enhanced_code'] ?? '')),
                'event_id' => trim((string) ($event['event_id'] ?? '')),
                'raw_payload' => $event,
            ];
        }

        return $normalized;
    }

    public function healthMetadata(): array
    {
        return [
            'provider' => 'log',
            'mode' => 'simulation',
            'enabled' => true,
        ];
    }
}
