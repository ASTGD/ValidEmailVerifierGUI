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
        $schemaVersion = strtolower(trim((string) ($payload['schema_version'] ?? 'v2')));
        if (! in_array($schemaVersion, ['v2', 'v3'], true)) {
            $errors[] = 'Payload field "schema_version" must be either v2 or v3.';
        }

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

        if ($schemaVersion === 'v3') {
            $errors = array_merge($errors, $this->validateV3Rules($profiles));
            $errors = array_merge($errors, $this->validateV3Modes($payload));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $profiles
     * @return array<int, string>
     */
    private function validateV3Rules(array $profiles): array
    {
        $errors = [];

        foreach ($profiles as $profileName => $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $session = $profile['session'] ?? null;
            if (! is_array($session)) {
                $errors[] = sprintf('Payload field "profiles.%s.session" is required for schema v3.', $profileName);
            } else {
                foreach ([
                    'max_concurrency',
                    'connects_per_minute',
                    'reuse_connection_for_retries',
                    'retry_jitter_percent',
                    'ehlo_profile',
                ] as $field) {
                    if (! array_key_exists($field, $session)) {
                        $errors[] = sprintf('Payload field "profiles.%s.session.%s" is required for schema v3.', $profileName, $field);
                    }
                }
            }

            foreach (['enhanced_rules', 'smtp_code_rules', 'message_rules'] as $ruleCollection) {
                $rules = $profile[$ruleCollection] ?? null;
                if (! is_array($rules)) {
                    continue;
                }

                foreach ($rules as $ruleIndex => $rule) {
                    if (! is_array($rule)) {
                        continue;
                    }

                    foreach (['rule_tag', 'confidence_hint', 'provider_scope'] as $field) {
                        if (! array_key_exists($field, $rule) || trim((string) $rule[$field]) === '') {
                            $errors[] = sprintf(
                                'Payload rule field "profiles.%s.%s.%d.%s" is required for schema v3.',
                                $profileName,
                                $ruleCollection,
                                $ruleIndex,
                                $field
                            );
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function validateV3Modes(array $payload): array
    {
        $errors = [];
        $modes = $payload['modes'] ?? null;
        if (! is_array($modes)) {
            return ['Payload field "modes" is required and must be an object for schema v3.'];
        }

        foreach (['normal', 'cautious', 'drain', 'quarantine', 'degraded_probe'] as $mode) {
            $definition = $modes[$mode] ?? null;
            if (! is_array($definition)) {
                $errors[] = sprintf('Payload field "modes.%s" is required for schema v3.', $mode);

                continue;
            }

            foreach (['probe_enabled', 'max_concurrency_multiplier', 'connects_per_minute_multiplier'] as $field) {
                if (! array_key_exists($field, $definition)) {
                    $errors[] = sprintf('Payload field "modes.%s.%s" is required for schema v3.', $mode, $field);
                }
            }
        }

        return $errors;
    }
}
