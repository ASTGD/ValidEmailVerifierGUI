<?php

namespace App\Support;

use App\Models\User;

class EnhancedModeGate
{
    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public static function evaluate(?User $user): array
    {
        if (! EngineSettings::enhancedModeEnabled()) {
            return ['allowed' => false, 'reason' => 'coming_soon'];
        }

        $policies = EnginePolicySettings::policies();
        $enhancedEnabled = (bool) ($policies['enhanced']['enabled'] ?? false);

        if (! $enhancedEnabled) {
            return ['allowed' => false, 'reason' => 'policy_disabled'];
        }

        if ((bool) config('engine.enhanced_requires_entitlement', false)) {
            if (! $user || ! $user->enhanced_enabled) {
                return ['allowed' => false, 'reason' => 'upgrade_required'];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    public static function canUse(?User $user): bool
    {
        return self::evaluate($user)['allowed'];
    }

    public static function message(?User $user): string
    {
        $reason = self::evaluate($user)['reason'];

        return match ($reason) {
            'coming_soon' => __('Enhanced mode is coming soon.'),
            'policy_disabled' => __('Enhanced mode is temporarily unavailable.'),
            'upgrade_required' => __('Enhanced mode requires an upgraded plan.'),
            default => __('Enhanced mode is unavailable.'),
        };
    }

    public static function helperText(?User $user): string
    {
        $reason = self::evaluate($user)['reason'];

        return match ($reason) {
            'coming_soon' => __('Coming soon.'),
            'policy_disabled' => __('Temporarily unavailable.'),
            'upgrade_required' => __('Upgrade or contact support.'),
            default => __('Unavailable.'),
        };
    }
}
