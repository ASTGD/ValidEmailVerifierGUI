<?php

namespace App\Services\VerifierPolicy;

use App\Models\SmtpPolicyActionAudit;
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
        $modeSemanticsHash = $this->resolveModeSemanticsHash($payload);

        $policyVersion->fill([
            'schema_version' => $this->resolveSchemaVersion($payload),
            'validation_status' => $errors === [] ? 'valid' : 'invalid',
            'validation_errors' => $errors === [] ? null : $errors,
            'validated_at' => Carbon::now(),
            'validated_by' => $validatedBy,
            'mode_semantics_hash' => $modeSemanticsHash,
        ]);
        $policyVersion->save();

        AdminAuditLogger::log('smtp_policy_version_validated', $policyVersion, [
            'policy_version' => $policyVersion->version,
            'validation_status' => $policyVersion->validation_status,
            'validated_by' => $validatedBy,
            'validation_errors' => $errors,
            'schema_version' => $policyVersion->schema_version,
            'mode_semantics_hash' => $policyVersion->mode_semantics_hash,
        ]);
        SmtpPolicyActionAudit::query()->create([
            'action' => 'validate',
            'policy_version' => $policyVersion->version,
            'provider' => 'generic',
            'source' => 'lifecycle',
            'actor' => $validatedBy,
            'result' => $errors === [] ? 'success' : 'failed',
            'context' => [
                'validation_status' => $policyVersion->validation_status,
                'errors' => $errors,
                'schema_version' => $policyVersion->schema_version,
                'mode_semantics_hash' => $policyVersion->mode_semantics_hash,
            ],
            'created_at' => now(),
        ]);

        return [
            'record' => $policyVersion->refresh(),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveSchemaVersion(array $payload): string
    {
        $schemaVersion = strtolower(trim((string) ($payload['schema_version'] ?? '')));

        return $schemaVersion === 'v3' ? 'v3' : 'v2';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveModeSemanticsHash(array $payload): ?string
    {
        $modes = $payload['modes'] ?? null;
        if (! is_array($modes) || $modes === []) {
            return null;
        }

        $normalized = $this->sortRecursive($modes);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return null;
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function sortRecursive(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        return $value;
    }
}
