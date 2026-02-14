<?php

namespace Tests\Feature;

use App\Services\VerifierPolicy\SmtpPolicyPayloadValidator;
use Tests\TestCase;

class SmtpPolicyPayloadValidatorTest extends TestCase
{
    public function test_validator_accepts_v2_payload_without_v3_only_fields(): void
    {
        $payload = [
            'enabled' => true,
            'version' => 'v2.5.0',
            'profiles' => [
                'generic' => [
                    'retry' => [
                        'default_seconds' => 60,
                        'tempfail_seconds' => 90,
                        'greylist_seconds' => 180,
                        'policy_blocked_seconds' => 300,
                        'unknown_seconds' => 75,
                    ],
                ],
            ],
        ];

        $errors = app(SmtpPolicyPayloadValidator::class)->validate($payload, 'v2.5.0');

        $this->assertSame([], $errors);
    }

    public function test_validator_requires_v3_mode_semantics_and_rule_metadata(): void
    {
        $payload = [
            'schema_version' => 'v3',
            'enabled' => true,
            'version' => 'v3.2.0',
            'profiles' => [
                'generic' => [
                    'enhanced_rules' => [
                        [
                            'enhanced_prefixes' => ['4.7.'],
                            'decision_class' => 'retryable',
                        ],
                    ],
                    'retry' => [
                        'default_seconds' => 60,
                        'tempfail_seconds' => 90,
                        'greylist_seconds' => 180,
                        'policy_blocked_seconds' => 300,
                        'unknown_seconds' => 75,
                    ],
                ],
            ],
        ];

        $errors = app(SmtpPolicyPayloadValidator::class)->validate($payload, 'v3.2.0');

        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsError($errors, 'profiles.generic.session'));
        $this->assertTrue($this->containsError($errors, 'rule_tag'));
        $this->assertTrue($this->containsError($errors, 'modes'));
    }

    public function test_validator_rejects_unsupported_rule_tag_in_v4_payload(): void
    {
        $payload = [
            'schema_version' => 'v4',
            'enabled' => true,
            'version' => 'v4.0.0',
            'modes' => [
                'normal' => [
                    'probe_enabled' => true,
                    'max_concurrency_multiplier' => 1,
                    'connects_per_minute_multiplier' => 1,
                ],
                'cautious' => [
                    'probe_enabled' => true,
                    'max_concurrency_multiplier' => 0.7,
                    'connects_per_minute_multiplier' => 0.6,
                ],
                'drain' => [
                    'probe_enabled' => false,
                    'max_concurrency_multiplier' => 0,
                    'connects_per_minute_multiplier' => 0,
                ],
                'quarantine' => [
                    'probe_enabled' => false,
                    'max_concurrency_multiplier' => 0,
                    'connects_per_minute_multiplier' => 0,
                ],
                'degraded_probe' => [
                    'probe_enabled' => true,
                    'max_concurrency_multiplier' => 0.5,
                    'connects_per_minute_multiplier' => 0.5,
                ],
            ],
            'profiles' => [
                'generic' => [
                    'enhanced_rules' => [
                        [
                            'rule_id' => 'generic-enhanced-test',
                            'enhanced_prefixes' => ['4.7.'],
                            'decision_class' => 'retryable',
                            'category' => 'risky',
                            'reason' => 'smtp_tempfail',
                            'reason_code' => 'smtp_tempfail',
                            'rule_tag' => 'unsupported_tag',
                            'confidence_hint' => 'medium',
                            'provider_scope' => 'generic',
                        ],
                    ],
                    'retry' => [
                        'default_seconds' => 60,
                        'tempfail_seconds' => 90,
                        'greylist_seconds' => 180,
                        'policy_blocked_seconds' => 300,
                        'unknown_seconds' => 75,
                    ],
                    'session' => [
                        'max_concurrency' => 2,
                        'connects_per_minute' => 30,
                        'reuse_connection_for_retries' => true,
                        'retry_jitter_percent' => 15,
                        'ehlo_profile' => 'default',
                    ],
                ],
            ],
        ];

        $errors = app(SmtpPolicyPayloadValidator::class)->validate($payload, 'v4.0.0');

        $this->assertNotEmpty($errors);
        $this->assertTrue($this->containsError($errors, 'unsupported value'));
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function containsError(array $errors, string $needle): bool
    {
        foreach ($errors as $error) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }
}
