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

    public static function cacheOnlyEnabled(): bool
    {
        return self::boolValue('cache_only_mode_enabled', (bool) config('engine.cache_only_mode_enabled', false));
    }

    public static function cacheOnlyMissStatus(): string
    {
        $status = self::stringValue('cache_only_miss_status', (string) config('engine.cache_only_miss_status', 'risky'));
        $status = strtolower(trim($status));

        return in_array($status, ['valid', 'invalid', 'risky'], true) ? $status : 'risky';
    }

    public static function cacheCapacityMode(): string
    {
        $mode = self::stringValue('cache_capacity_mode', (string) config('engine.cache_capacity_mode', 'on_demand'));
        $mode = strtolower(trim($mode));

        return in_array($mode, ['on_demand', 'provisioned'], true) ? $mode : 'on_demand';
    }

    public static function cacheBatchSize(): int
    {
        $value = self::intValue('cache_batch_size', (int) config('engine.cache_batch_size', 100));

        return max(1, min(100, $value));
    }

    public static function cacheConsistentRead(): bool
    {
        return self::boolValue('cache_consistent_read', (bool) config('engine.cache_consistent_read', false));
    }

    public static function cacheOnDemandMaxBatchesPerSecond(): ?int
    {
        $value = self::intValue('cache_ondemand_max_batches_per_second', (int) config('engine.cache_ondemand_max_batches_per_second', 0));

        return $value > 0 ? $value : null;
    }

    public static function cacheOnDemandSleepMsBetweenBatches(): int
    {
        return max(0, self::intValue('cache_ondemand_sleep_ms_between_batches', (int) config('engine.cache_ondemand_sleep_ms_between_batches', 0)));
    }

    public static function cacheProvisionedMaxBatchesPerSecond(): int
    {
        return max(1, self::intValue('cache_provisioned_max_batches_per_second', (int) config('engine.cache_provisioned_max_batches_per_second', 5)));
    }

    public static function cacheProvisionedSleepMsBetweenBatches(): int
    {
        return max(0, self::intValue('cache_provisioned_sleep_ms_between_batches', (int) config('engine.cache_provisioned_sleep_ms_between_batches', 100)));
    }

    public static function cacheProvisionedMaxRetries(): int
    {
        return max(0, self::intValue('cache_provisioned_max_retries', (int) config('engine.cache_provisioned_max_retries', 5)));
    }

    public static function cacheProvisionedBackoffBaseMs(): int
    {
        return max(0, self::intValue('cache_provisioned_backoff_base_ms', (int) config('engine.cache_provisioned_backoff_base_ms', 200)));
    }

    public static function cacheProvisionedBackoffMaxMs(): int
    {
        return max(0, self::intValue('cache_provisioned_backoff_max_ms', (int) config('engine.cache_provisioned_backoff_max_ms', 2000)));
    }

    public static function cacheProvisionedJitterEnabled(): bool
    {
        return self::boolValue('cache_provisioned_jitter_enabled', (bool) config('engine.cache_provisioned_jitter_enabled', true));
    }

    public static function cacheFailureMode(): string
    {
        $mode = self::stringValue('cache_failure_mode', (string) config('engine.cache_failure_mode', 'fail_job'));
        $mode = strtolower(trim($mode));

        return in_array($mode, ['fail_job', 'treat_miss', 'skip_cache'], true) ? $mode : 'fail_job';
    }

    public static function cacheWritebackEnabled(): bool
    {
        return self::boolValue('cache_writeback_enabled', (bool) config('engine.cache_writeback_enabled', false));
    }

    /**
     * @return array<int, string>
     */
    public static function cacheWritebackStatuses(): array
    {
        $default = config('engine.cache_writeback_statuses', ['valid', 'invalid']);

        if (is_string($default)) {
            $default = array_filter(array_map('trim', explode(',', $default)));
        }

        $value = self::arrayValue('cache_writeback_statuses', is_array($default) ? $default : []);
        $normalized = array_values(array_unique(array_filter(array_map(static function ($status): string {
            return strtolower(trim((string) $status));
        }, $value))));

        $allowed = ['valid', 'invalid'];

        return array_values(array_intersect($normalized, $allowed));
    }

    public static function cacheWritebackBatchSize(): int
    {
        $value = self::intValue('cache_writeback_batch_size', (int) config('engine.cache_writeback_batch_size', 25));

        return max(1, min(25, $value));
    }

    public static function cacheWritebackMaxWritesPerSecond(): ?int
    {
        $value = self::intValue('cache_writeback_max_writes_per_second', (int) config('engine.cache_writeback_max_writes_per_second', 0));

        return $value > 0 ? $value : null;
    }

    public static function cacheWritebackRetryAttempts(): int
    {
        $value = self::intValue('cache_writeback_retry_attempts', (int) config('engine.cache_writeback_retry_attempts', 5));

        return max(0, $value);
    }

    public static function cacheWritebackBackoffBaseMs(): int
    {
        return max(0, self::intValue('cache_writeback_backoff_base_ms', (int) config('engine.cache_writeback_backoff_base_ms', 200)));
    }

    public static function cacheWritebackBackoffMaxMs(): int
    {
        return max(0, self::intValue('cache_writeback_backoff_max_ms', (int) config('engine.cache_writeback_backoff_max_ms', 2000)));
    }

    public static function cacheWritebackFailureMode(): string
    {
        $mode = self::stringValue('cache_writeback_failure_mode', (string) config('engine.cache_writeback_failure_mode', 'fail_job'));
        $mode = strtolower(trim($mode));

        return in_array($mode, ['fail_job', 'skip_writes', 'continue'], true) ? $mode : 'fail_job';
    }

    public static function cacheWritebackTestEnabled(): bool
    {
        return self::boolValue('cache_writeback_test_mode_enabled', (bool) config('engine.cache_writeback_test_mode_enabled', false));
    }

    public static function cacheWritebackTestTable(): ?string
    {
        $value = self::stringValue('cache_writeback_test_table', (string) config('engine.cache_writeback_test_table', ''));
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function cacheWritebackTestResult(): string
    {
        $value = self::stringValue('cache_writeback_test_result', (string) config('engine.cache_writeback_test_result', 'Cache_miss'));
        $value = trim($value);

        return $value === '' ? 'Cache_miss' : $value;
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

    public static function showSingleChecksInAdmin(): bool
    {
        return self::boolValue('show_single_checks_in_admin', (bool) config('engine.show_single_checks_in_admin', false));
    }

    public static function monitorEnabled(): bool
    {
        return self::boolValue('monitor_enabled', (bool) config('engine.monitor_enabled', false));
    }

    public static function monitorIntervalMinutes(): int
    {
        $value = self::stringValue('monitor_interval_minutes', '');
        if ($value === '') {
            return (int) config('engine.monitor_interval_minutes', 60);
        }

        return max(1, (int) $value);
    }

    /**
     * @return array<int, string>
     */
    public static function monitorRblList(): array
    {
        $raw = self::stringValue('monitor_rbl_list', (string) config('engine.monitor_rbl_list', ''));
        $entries = array_map('trim', explode(',', $raw));

        return array_values(array_unique(array_filter(array_map(static function (string $value): string {
            $value = strtolower(trim($value));
            $value = ltrim($value, '.');

            return $value;
        }, $entries))));
    }

    public static function monitorDnsMode(): string
    {
        $mode = self::stringValue('monitor_dns_mode', (string) config('engine.monitor_dns_mode', 'system'));
        $mode = strtolower(trim($mode));

        if (! in_array($mode, ['system', 'custom'], true)) {
            return 'system';
        }

        return $mode;
    }

    public static function monitorDnsServerIp(): ?string
    {
        $value = self::stringValue('monitor_dns_server_ip', (string) config('engine.monitor_dns_server_ip', ''));
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function monitorDnsServerPort(): int
    {
        $value = self::stringValue('monitor_dns_server_port', '');
        if ($value === '') {
            return (int) config('engine.monitor_dns_server_port', 53);
        }

        $port = (int) $value;

        if ($port < 1 || $port > 65535) {
            return (int) config('engine.monitor_dns_server_port', 53);
        }

        return $port;
    }

    public static function metricsSource(): string
    {
        $source = self::stringValue('metrics_source', (string) config('engine.metrics_source', 'container'));
        $source = strtolower(trim($source));

        return in_array($source, ['container', 'host'], true) ? $source : 'container';
    }

    public static function queueConnection(): string
    {
        $default = (string) config('queue.default', 'database');
        $value = self::stringValue('queue_connection', '');
        $value = $value === '' ? $default : strtolower(trim($value));

        return in_array($value, ['redis', 'database', 'sync'], true) ? $value : $default;
    }

    public static function cacheStore(): string
    {
        $default = (string) config('cache.default', 'database');
        $value = self::stringValue('cache_store', '');
        $value = $value === '' ? $default : strtolower(trim($value));

        return in_array($value, ['redis', 'database', 'file', 'array'], true) ? $value : $default;
    }

    public static function horizonEnabled(): bool
    {
        return self::boolValue('horizon_enabled', false);
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

    private static function intValue(string $field, int $default): int
    {
        if (! Schema::hasTable('engine_settings') || ! Schema::hasColumn('engine_settings', $field)) {
            return $default;
        }

        $value = EngineSetting::query()->value($field);

        if (is_null($value)) {
            return $default;
        }

        return (int) $value;
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
