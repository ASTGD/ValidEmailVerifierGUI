<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Support\EnginePolicySettings;
use App\Support\EngineSettings;
use Illuminate\Http\JsonResponse;

class VerifierPolicyController
{
    public function __invoke(): JsonResponse
    {
        $policies = EnginePolicySettings::policies();

        return response()->json([
            'data' => [
                'contract_version' => (string) config('engine.policy_contract_version', 'v1'),
                'engine_paused' => EngineSettings::enginePaused(),
                'enhanced_mode_enabled' => EngineSettings::enhancedModeEnabled(),
                'role_accounts_behavior' => EngineSettings::roleAccountsBehavior(),
                'role_accounts_list' => EngineSettings::roleAccountsList(),
                'provider_policies' => EngineSettings::providerPolicies(),
                'policies' => [
                    'standard' => $policies['standard'] ?? [],
                    'enhanced' => $policies['enhanced'] ?? [],
                ],
            ],
        ]);
    }
}
