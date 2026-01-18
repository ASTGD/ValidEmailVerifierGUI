<?php

namespace App\Support;

use App\Models\EngineSetting;
use Illuminate\Support\Facades\Schema;

class EngineSettings
{
    public static function enginePaused(): bool
    {
        return self::value('engine_paused', (bool) config('engine.engine_paused', false));
    }

    public static function enhancedModeEnabled(): bool
    {
        return self::value('enhanced_mode_enabled', (bool) config('engine.enhanced_mode_enabled', false));
    }

    private static function value(string $field, bool $default): bool
    {
        if (! Schema::hasTable('engine_settings')) {
            return $default;
        }

        $value = EngineSetting::query()->value($field);

        return is_null($value) ? $default : (bool) $value;
    }
}
