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
}
