<?php

namespace App\Support;

class SmtpProbeStage
{
    /**
     * @return array{enabled: bool, reason: string|null}
     */
    public static function evaluate(): array
    {
        if (! EngineSettings::enhancedModeEnabled()) {
            return ['enabled' => false, 'reason' => 'enhanced_mode_disabled'];
        }

        $policies = EnginePolicySettings::policies();
        $enhancedPolicyEnabled = (bool) ($policies['enhanced']['enabled'] ?? false);

        if (! $enhancedPolicyEnabled) {
            return ['enabled' => false, 'reason' => 'enhanced_policy_disabled'];
        }

        return ['enabled' => true, 'reason' => null];
    }

    public static function enabled(): bool
    {
        return self::evaluate()['enabled'];
    }
}
