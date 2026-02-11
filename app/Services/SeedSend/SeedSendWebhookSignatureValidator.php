<?php

namespace App\Services\SeedSend;

use App\Services\SeedSend\Providers\SeedSendProviderManager;
use Illuminate\Http\Request;

class SeedSendWebhookSignatureValidator
{
    public function __construct(private SeedSendProviderManager $providerManager) {}

    public function validate(Request $request, string $provider): bool
    {
        $secret = $this->providerManager->webhookSecretForProvider($provider);
        if ($secret === '') {
            return false;
        }

        $headerName = trim((string) config('seed_send.webhooks.signature_header', 'X-Seed-Signature'));
        $signature = trim((string) $request->header($headerName, ''));
        if ($signature === '') {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
