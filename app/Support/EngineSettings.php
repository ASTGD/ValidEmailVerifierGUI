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

    public static function tempfailRetryEnabled(): bool
    {
        return self::boolValue('tempfail_retry_enabled', (bool) config('engine.tempfail_retry_enabled', false));
    }

    public static function tempfailRetryMaxAttempts(): int
    {
        $value = self::stringValue('tempfail_retry_max_attempts', '');
        if ($value === '') {
            return (int) config('engine.tempfail_retry_max_attempts', 2);
        }

        return max(0, (int) $value);
    }

    /**
     * @return array<int, int>
     */
    public static function tempfailRetryBackoffMinutes(): array
    {
        $raw = self::stringValue('tempfail_retry_backoff_minutes', (string) config('engine.tempfail_retry_backoff_minutes', ''));

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $values = array_values(array_filter(array_map(static function (string $value): int {
            $value = trim($value);
            if ($value === '' || ! is_numeric($value)) {
                return 0;
            }

            return max(0, (int) $value);
        }, $parts)));

        if ($values === []) {
            return [10];
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    public static function tempfailRetryReasons(): array
    {
        $raw = self::stringValue(
            'tempfail_retry_reasons',
            (string) config('engine.tempfail_retry_reasons', '')
        );

        $parts = array_filter(array_map('trim', explode(',', $raw)));

        return array_values(array_unique(array_filter(array_map('strtolower', $parts))));
    }

    public static function reputationWindowHours(): int
    {
        $value = self::stringValue('reputation_window_hours', '');
        if ($value === '') {
            return (int) config('engine.reputation_window_hours', 24);
        }

        return max(1, (int) $value);
    }

    public static function reputationMinSamples(): int
    {
        $value = self::stringValue('reputation_min_samples', '');
        if ($value === '') {
            return (int) config('engine.reputation_min_samples', 100);
        }

        return max(1, (int) $value);
    }

    public static function reputationTempfailWarnRate(): float
    {
        $value = self::stringValue('reputation_tempfail_warn_rate', '');
        if ($value === '') {
            return (float) config('engine.reputation_tempfail_warn_rate', 0.2);
        }

        return max(0.0, (float) $value);
    }

    public static function reputationTempfailCriticalRate(): float
    {
        $value = self::stringValue('reputation_tempfail_critical_rate', '');
        if ($value === '') {
            return (float) config('engine.reputation_tempfail_critical_rate', 0.4);
        }

        return max(0.0, (float) $value);
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
