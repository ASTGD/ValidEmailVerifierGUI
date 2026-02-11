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

    public function isEnabled(string $provider): bool
    {
        $normalizedProvider = $this->normalizeProvider($provider);

        return (bool) config("seed_send.provider.providers.{$normalizedProvider}.enabled", false);
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return $provider === '' ? 'log' : $provider;
    }
}
