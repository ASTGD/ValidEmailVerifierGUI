<?php

namespace App\Support;

use App\Models\EngineVerificationPolicy;
use Illuminate\Support\Facades\Schema;

class EnginePolicySettings
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function policies(): array
    {
        $defaults = config('engine.policy_defaults', []);

        if (! Schema::hasTable('engine_verification_policies')) {
            return self::normalizeDefaults($defaults);
        }

        $records = EngineVerificationPolicy::query()
            ->get()
            ->keyBy(fn (EngineVerificationPolicy $policy): string => (string) $policy->mode);

        $policies = [];

        foreach (['standard', 'enhanced'] as $mode) {
            $policy = $records->get($mode);
            $fallback = is_array($defaults[$mode] ?? null) ? $defaults[$mode] : [];
            $policies[$mode] = self::policyToArray($mode, $policy, $fallback);
        }

        return $policies;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeDefaults(array $defaults): array
    {
        $normalized = [];

        foreach (['standard', 'enhanced'] as $mode) {
            $fallback = is_array($defaults[$mode] ?? null) ? $defaults[$mode] : [];
            $normalized[$mode] = self::policyToArray($mode, null, $fallback);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private static function policyToArray(string $mode, ?EngineVerificationPolicy $policy, array $fallback): array
    {
        return [
            'mode' => $mode,
            'enabled' => $policy?->enabled ?? (bool) ($fallback['enabled'] ?? false),
            'dns_timeout_ms' => $policy?->dns_timeout_ms ?? (int) ($fallback['dns_timeout_ms'] ?? 0),
            'smtp_connect_timeout_ms' => $policy?->smtp_connect_timeout_ms ?? (int) ($fallback['smtp_connect_timeout_ms'] ?? 0),
            'smtp_read_timeout_ms' => $policy?->smtp_read_timeout_ms ?? (int) ($fallback['smtp_read_timeout_ms'] ?? 0),
            'max_mx_attempts' => $policy?->max_mx_attempts ?? (int) ($fallback['max_mx_attempts'] ?? 0),
            'max_concurrency_default' => $policy?->max_concurrency_default ?? (int) ($fallback['max_concurrency_default'] ?? 0),
            'per_domain_concurrency' => $policy?->per_domain_concurrency ?? (int) ($fallback['per_domain_concurrency'] ?? 0),
            'global_connects_per_minute' => $policy?->global_connects_per_minute ?? ($fallback['global_connects_per_minute'] ?? null),
            'tempfail_backoff_seconds' => $policy?->tempfail_backoff_seconds ?? ($fallback['tempfail_backoff_seconds'] ?? null),
            'circuit_breaker_tempfail_rate' => $policy?->circuit_breaker_tempfail_rate ?? ($fallback['circuit_breaker_tempfail_rate'] ?? null),
        ];
    }
}
