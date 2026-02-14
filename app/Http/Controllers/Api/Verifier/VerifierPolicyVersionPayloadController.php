<?php

namespace App\Http\Controllers\Api\Verifier;

use App\Models\SmtpPolicyVersion;
use Illuminate\Http\JsonResponse;

class VerifierPolicyVersionPayloadController
{
    public function __invoke(string $version): JsonResponse
    {
        $normalizedVersion = trim($version);
        if ($normalizedVersion === '') {
            abort(404);
        }

        $policyVersion = SmtpPolicyVersion::query()
            ->where('version', $normalizedVersion)
            ->first();

        if (! $policyVersion || ! is_array($policyVersion->policy_payload) || $policyVersion->policy_payload === []) {
            abort(404);
        }

        if ((string) ($policyVersion->validation_status ?? 'pending') !== 'valid') {
            abort(404);
        }

        return response()->json([
            'data' => [
                'version' => $policyVersion->version,
                'is_active' => (bool) $policyVersion->is_active,
                'status' => (string) $policyVersion->status,
                'validation_status' => (string) ($policyVersion->validation_status ?? 'pending'),
                'policy_payload' => $policyVersion->policy_payload,
            ],
        ]);
    }
}
