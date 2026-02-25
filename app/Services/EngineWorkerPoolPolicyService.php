<?php

namespace App\Services;

use App\Models\EngineWorkerPool;
use Illuminate\Support\Facades\Cache;

class EngineWorkerPoolPolicyService
{
    public function providerProfileForPool(?string $poolSlug, string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, EngineWorkerPool::PROVIDERS, true)) {
            return 'standard';
        }

        $poolSlug = strtolower(trim((string) $poolSlug));
        if ($poolSlug === '') {
            $poolSlug = EngineWorkerPool::resolveDefaultSlug();
        }

        $cacheKey = 'engine_worker_pool:profiles:'.$poolSlug;
        $profiles = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($poolSlug): array {
            $pool = EngineWorkerPool::query()
                ->active()
                ->where('slug', $poolSlug)
                ->first();

            if (! $pool) {
                $fallback = EngineWorkerPool::query()->where('is_default', true)->first();

                return $fallback?->normalizedProviderProfiles() ?? EngineWorkerPool::defaultProviderProfiles();
            }

            return $pool->normalizedProviderProfiles();
        });

        $profile = strtolower(trim((string) ($profiles[$provider] ?? 'standard')));

        return in_array($profile, EngineWorkerPool::PROFILES, true) ? $profile : 'standard';
    }

    public function routingBonus(string $profile): int
    {
        $profile = strtolower(trim($profile));
        $bonus = (int) data_get(config('engine.pool_policy_profiles', []), $profile.'.routing_bonus', 0);

        return max(0, $bonus);
    }
}
