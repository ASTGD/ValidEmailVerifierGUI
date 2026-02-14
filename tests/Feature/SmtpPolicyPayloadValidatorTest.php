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
