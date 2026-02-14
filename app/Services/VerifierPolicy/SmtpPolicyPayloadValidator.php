<?php

namespace App\Services\VerifierPolicy;

class SmtpPolicyPayloadValidator
{
    /**
     * @return array<int, string>
     */
    public function validate(array $payload, string $expectedVersion): array
    {
        $errors = [];

        if (! array_key_exists('enabled', $payload) || ! is_bool($payload['enabled'])) {
            $errors[] = 'Payload field "enabled" must be present and boolean.';
        }

        $payloadVersion = trim((string) ($payload['version'] ?? ''));
        if ($payloadVersion === '') {
            $errors[] = 'Payload field "version" is required.';
        } elseif ($payloadVersion !== trim($expectedVersion)) {
            $errors[] = sprintf('Payload version "%s" does not match expected version "%s".', $payloadVersion, trim($expectedVersion));
        }

        $profiles = $payload['profiles'] ?? null;
        if (! is_array($profiles)) {
            $errors[] = 'Payload field "profiles" must be an object.';

            return $errors;
        }

        $genericProfile = $profiles['generic'] ?? null;
        if (! is_array($genericProfile)) {
            $errors[] = 'Payload field "profiles.generic" is required and must be an object.';

            return $errors;
        }

        $retry = $genericProfile['retry'] ?? null;
        if (! is_array($retry)) {
            $errors[] = 'Payload field "profiles.generic.retry" is required and must be an object.';

            return $errors;
        }

        foreach ([
            'default_seconds',
            'tempfail_seconds',
            'greylist_seconds',
            'policy_blocked_seconds',
            'unknown_seconds',
        ] as $retryField) {
            if (! array_key_exists($retryField, $retry) || ! is_numeric($retry[$retryField])) {
                $errors[] = sprintf('Payload retry field "%s" must be present and numeric.', $retryField);
            }
        }

        return $errors;
    }
}
