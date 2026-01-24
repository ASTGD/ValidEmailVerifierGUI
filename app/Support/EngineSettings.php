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
}
