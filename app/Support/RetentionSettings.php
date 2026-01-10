<?php

namespace App\Support;

use App\Models\RetentionSetting;
use Illuminate\Support\Facades\Schema;

class RetentionSettings
{
    public static function days(): int
    {
        $defaultDays = (int) config('verifier.retention_days', 30);

        if (! Schema::hasTable('retention_settings')) {
            return $defaultDays;
        }

        $value = RetentionSetting::query()->value('retention_days');

        return is_null($value) ? $defaultDays : (int) $value;
    }
}
