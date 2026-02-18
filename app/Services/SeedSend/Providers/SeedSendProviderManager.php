<?php

namespace App\Services\SeedSend\Providers;

use App\Contracts\SeedSendProvider;
use InvalidArgumentException;

class SeedSendProviderManager
{
    public function provider(string $provider): SeedSendProvider
    {
        $normalizedProvider = $this->normalizeProvider($provider);

        return match ($normalizedProvider) {
            'log' => app(LogSeedSendProvider::class),
            'sendgrid' => app(SendGridSeedSendProvider::class),
            default => throw new InvalidArgumentException(sprintf('Unsupported seed-send provider [%s].', $provider)),
        };
    }

    public function defaultProvider(): string
    {
        return $this->normalizeProvider((string) config('seed_send.provider.default', 'log'));
    }

    public function webhookSecretForProvider(string $provider): string
    {
        $normalizedProvider = $this->normalizeProvider($provider);

        return trim((string) config("seed_send.provider.providers.{$normalizedProvider}.webhook_secret", ''));
    }

    public function webhookPublicKeyForProvider(string $provider): string
    {
        $normalizedProvider = $this->normalizeProvider($provider);

        return trim((string) config("seed_send.provider.providers.{$normalizedProvider}.webhook_public_key", ''));
    }

    public function isEnabled(string $provider): bool
    {
        $normalizedProvider = $this->normalizeProvider($provider);

        return (bool) config("seed_send.provider.providers.{$normalizedProvider}.enabled", false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizeWebhookEvents(string $provider, mixed $payload): array
    {
        return $this->provider($provider)->normalizeWebhookEvents($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function providerHealthMetadata(string $provider): array
    {
        return $this->provider($provider)->healthMetadata();
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return $provider === '' ? 'log' : $provider;
    }
}
