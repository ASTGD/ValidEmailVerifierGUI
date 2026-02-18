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
        $provider = strtolower(trim($provider));

        if ($provider === 'sendgrid') {
            return $this->validateSendGridSignature($request, $provider)
                || $this->validateSharedSecretSignature($request, $provider);
        }

        return $this->validateSharedSecretSignature($request, $provider);
    }

    private function validateSharedSecretSignature(Request $request, string $provider): bool
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

        $cacheKey = $this->nonceCacheKey($provider, $nonce);
        $ttl = now()->addSeconds($maxAgeSeconds + 5);

        return Cache::add($cacheKey, (string) $timestamp, $ttl);
    }

    private function validateSendGridSignature(Request $request, string $provider): bool
    {
        $publicKey = $this->providerManager->webhookPublicKeyForProvider($provider);
        if ($publicKey === '') {
            return false;
        }

        $signatureHeader = trim((string) config('seed_send.provider.providers.sendgrid.signature_header', 'X-Twilio-Email-Event-Webhook-Signature'));
        $timestampHeader = trim((string) config('seed_send.provider.providers.sendgrid.timestamp_header', 'X-Twilio-Email-Event-Webhook-Timestamp'));

        $signatureRaw = trim((string) $request->header($signatureHeader, ''));
        $timestampRaw = trim((string) $request->header($timestampHeader, ''));
        if ($signatureRaw === '' || $timestampRaw === '' || ! ctype_digit($timestampRaw)) {
            return false;
        }

        $timestamp = (int) $timestampRaw;
        $maxAgeSeconds = max(1, (int) config('seed_send.webhooks.signature_max_age_seconds', 300));
        if (abs(now()->timestamp - $timestamp) > $maxAgeSeconds) {
            return false;
        }

        $decodedSignature = base64_decode($signatureRaw, true);
        if (! is_string($decodedSignature) || $decodedSignature === '') {
            return false;
        }

        $signedPayload = $timestampRaw.$request->getContent();
        $verified = openssl_verify($signedPayload, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return false;
        }

        $nonce = hash('sha256', implode('|', [
            $timestampRaw,
            $signatureRaw,
            hash('sha256', $request->getContent()),
        ]));

        return Cache::add(
            $this->nonceCacheKey($provider, $nonce),
            (string) $timestamp,
            now()->addSeconds($maxAgeSeconds + 5)
        );
    }

    private function nonceCacheKey(string $provider, string $nonce): string
    {
        $cachePrefix = trim((string) config('seed_send.webhooks.replay_cache_prefix', 'seed_send:webhook:nonce'));

        return sprintf('%s:%s:%s', $cachePrefix, strtolower(trim($provider)), $nonce);
    }
}
