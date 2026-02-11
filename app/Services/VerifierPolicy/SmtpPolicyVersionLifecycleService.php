<?php

namespace App\Services\VerifierPolicy;

use App\Models\SmtpPolicyVersion;
use App\Support\AdminAuditLogger;
use Illuminate\Support\Carbon;

class SmtpPolicyVersionLifecycleService
{
    public function __construct(
        private readonly SmtpPolicyPayloadValidator $validator,
    ) {}

    /**
     * @return array{record:SmtpPolicyVersion,errors:array<int,string>}
     */
    public function validateAndPersist(SmtpPolicyVersion $policyVersion, ?string $validatedBy = null): array
    {
        $payload = is_array($policyVersion->policy_payload) ? $policyVersion->policy_payload : [];
        $errors = $this->validator->validate($payload, (string) $policyVersion->version);

        $policyVersion->fill([
            'validation_status' => $errors === [] ? 'valid' : 'invalid',
            'validation_errors' => $errors === [] ? null : $errors,
            'validated_at' => Carbon::now(),
            'validated_by' => $validatedBy,
        ]);
        $policyVersion->save();

        AdminAuditLogger::log('smtp_policy_version_validated', $policyVersion, [
            'policy_version' => $policyVersion->version,
            'validation_status' => $policyVersion->validation_status,
            'validated_by' => $validatedBy,
            'validation_errors' => $errors,
        ]);

        return [
            'record' => $policyVersion->refresh(),
            'errors' => $errors,
        ];
    }
}
