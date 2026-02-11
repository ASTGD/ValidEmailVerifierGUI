<?php

namespace App\Services\SeedSend;

use App\Services\SeedSend\Providers\SeedSendProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $timestampHeader = trim((string) config('seed_send.webhooks.timestamp_header', 'X-Seed-Timestamp'));
        $nonceHeader = trim((string) config('seed_send.webhooks.nonce_header', 'X-Seed-Nonce'));
        $timestampRaw = trim((string) $request->header($timestampHeader, ''));
        $nonce = trim((string) $request->header($nonceHeader, ''));
        if ($timestampRaw === '' || ! ctype_digit($timestampRaw) || $nonce === '') {
            return false;
        }

        $timestamp = (int) $timestampRaw;
        $maxAgeSeconds = max(1, (int) config('seed_send.webhooks.signature_max_age_seconds', 300));
        if (abs(now()->timestamp - $timestamp) > $maxAgeSeconds) {
            return false;
        }

        $payload = $request->getContent();
        $signedPayload = sprintf("%s\n%s\n%s", $timestampRaw, $nonce, $payload);
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $cachePrefix = trim((string) config('seed_send.webhooks.replay_cache_prefix', 'seed_send:webhook:nonce'));
        $cacheKey = sprintf('%s:%s:%s', $cachePrefix, strtolower(trim($provider)), $nonce);
        $ttl = now()->addSeconds($maxAgeSeconds);

        return Cache::add($cacheKey, (string) $timestamp, $ttl);
    }
}
