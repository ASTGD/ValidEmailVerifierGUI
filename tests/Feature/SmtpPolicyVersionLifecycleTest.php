<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SmtpPolicyVersion;
use App\Services\VerifierPolicy\SmtpPolicyVersionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpPolicyVersionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_service_marks_payload_valid_and_records_audit_entry(): void
    {
        $version = SmtpPolicyVersion::query()->create([
            'version' => 'v3.1.0',
            'status' => 'draft',
            'is_active' => false,
            'policy_payload' => [
                'schema_version' => 'v3',
                'enabled' => true,
                'version' => 'v3.1.0',
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
                                'rule_id' => 'generic-enhanced-47-retry',
                                'enhanced_prefixes' => ['4.7.'],
                                'decision_class' => 'retryable',
                                'category' => 'risky',
                                'reason' => 'smtp_tempfail',
                                'reason_code' => 'smtp_tempfail',
                                'rule_tag' => 'greylist',
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
            ],
        ]);

        $result = app(SmtpPolicyVersionLifecycleService::class)->validateAndPersist($version, 'admin@example.com');

        /** @var SmtpPolicyVersion $record */
        $record = $result['record'];
        /** @var array<int, string> $errors */
        $errors = $result['errors'];

        $this->assertSame([], $errors);
        $this->assertSame('valid', $record->validation_status);
        $this->assertSame('v3', $record->schema_version);
        $this->assertNotNull($record->mode_semantics_hash);
        $this->assertNotNull($record->validated_at);
        $this->assertSame('admin@example.com', $record->validated_by);
        $this->assertDatabaseHas(AdminAuditLog::class, [
            'action' => 'smtp_policy_version_validated',
            'subject_id' => (string) $record->id,
        ]);
    }

    public function test_validation_service_marks_payload_invalid_with_errors(): void
    {
        $version = SmtpPolicyVersion::query()->create([
            'version' => 'v3.2.0',
            'status' => 'draft',
            'is_active' => false,
            'policy_payload' => [
                'enabled' => true,
                'version' => 'v3.2.0',
                'profiles' => [
                    'generic' => [
                        'retry' => [
                            'default_seconds' => 60,
                        ],
                    ],
                ],
            ],
        ]);

        $result = app(SmtpPolicyVersionLifecycleService::class)->validateAndPersist($version, 'ops@example.com');

        /** @var SmtpPolicyVersion $record */
        $record = $result['record'];
        /** @var array<int, string> $errors */
        $errors = $result['errors'];

        $this->assertNotEmpty($errors);
        $this->assertSame('invalid', $record->validation_status);
        $this->assertSame($errors, $record->validation_errors);
        $this->assertNotNull($record->validated_at);
        $this->assertSame('ops@example.com', $record->validated_by);
    }
}
