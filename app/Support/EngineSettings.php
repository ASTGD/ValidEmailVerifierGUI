<?php

namespace App\Support;

use App\Models\EngineSetting;
use Illuminate\Support\Facades\Schema;

class EngineSettings
{
    public static function enginePaused(): bool
    {
        return self::boolValue('engine_paused', (bool) config('engine.engine_paused', false));
    }

    public static function enhancedModeEnabled(): bool
    {
        return self::boolValue('enhanced_mode_enabled', (bool) config('engine.enhanced_mode_enabled', false));
    }

    public static function roleAccountsBehavior(): string
    {
        $behavior = self::stringValue('role_accounts_behavior', (string) config('engine.role_accounts_behavior', 'risky'));
        if (! in_array($behavior, ['risky', 'allow'], true)) {
            return 'risky';
        }

        return $behavior;
    }

    /**
     * @return array<int, string>
     */
    public static function roleAccountsList(): array
    {
        $list = self::stringValue('role_accounts_list', (string) config('engine.role_accounts_list', ''));
        if ($list === '') {
            return [];
        }

        $entries = array_map('trim', explode(',', $list));

        return array_values(array_unique(array_filter(array_map('strtolower', $entries))));
    }

    public static function catchAllPolicy(): string
    {
        $policy = self::stringValue('catch_all_policy', (string) config('engine.catch_all_policy', 'risky_only'));

        if (! in_array($policy, ['risky_only', 'promote_if_score_gte'], true)) {
            return 'risky_only';
        }

        return $policy;
    }

    public static function catchAllPromoteThreshold(): ?int
    {
        $value = self::stringValue('catch_all_promote_threshold', '');

        if ($value === '') {
            $fallback = config('engine.catch_all_promote_threshold');

            return is_null($fallback) ? null : (int) $fallback;
        }

        $threshold = (int) $value;

        return $threshold >= 0 ? $threshold : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function providerPolicies(): array
    {
        $default = config('engine.provider_policies', []);
        $value = self::arrayValue('provider_policies', is_array($default) ? $default : []);

        return self::normalizeProviderPolicies($value);
    }

    private static function boolValue(string $field, bool $default): bool
    {
        if (! Schema::hasTable('engine_settings') || ! Schema::hasColumn('engine_settings', $field)) {
            return $default;
        }

        $value = EngineSetting::query()->value($field);

        return is_null($value) ? $default : (bool) $value;
    }

    private static function stringValue(string $field, string $default): string
    {
        if (! Schema::hasTable('engine_settings') || ! Schema::hasColumn('engine_settings', $field)) {
            return $default;
        }

        $value = EngineSetting::query()->value($field);

        return is_null($value) ? $default : (string) $value;
    }

    /**
     * @param  array<int, mixed>  $default
     * @return array<int, mixed>
     */
    private static function arrayValue(string $field, array $default): array
    {
        if (! Schema::hasTable('engine_settings') || ! Schema::hasColumn('engine_settings', $field)) {
            return $default;
        }

        $value = EngineSetting::query()->value($field);

        return is_array($value) ? $value : $default;
    }

    /**
     * @param  array<int, mixed>  $policies
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeProviderPolicies(array $policies): array
    {
        $normalized = [];

        foreach ($policies as $policy) {
            if (! is_array($policy)) {
                continue;
            }

            $name = trim((string) ($policy['name'] ?? ''));
            $domains = $policy['domains'] ?? [];

            if ($name === '' || ! is_array($domains)) {
                continue;
            }

            $domains = array_values(array_unique(array_filter(array_map(static function ($domain): string {
                $domain = strtolower(trim((string) $domain));
                $domain = ltrim($domain, '.');
                $domain = preg_replace('/^\*\./', '', $domain) ?? $domain;

                return $domain;
            }, $domains))));

            if ($domains === []) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'enabled' => (bool) ($policy['enabled'] ?? true),
                'domains' => $domains,
                'per_domain_concurrency' => self::optionalInt($policy['per_domain_concurrency'] ?? null),
                'connects_per_minute' => self::optionalInt($policy['connects_per_minute'] ?? null),
                'tempfail_backoff_seconds' => self::optionalInt($policy['tempfail_backoff_seconds'] ?? null),
                'retryable_network_retries' => self::optionalInt($policy['retryable_network_retries'] ?? null),
            ];
        }

        return $normalized;
    }

    private static function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
