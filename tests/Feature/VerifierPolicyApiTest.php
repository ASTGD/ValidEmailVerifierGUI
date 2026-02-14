<?php

namespace Tests\Feature;

use App\Models\EngineSetting;
use App\Models\SmtpPolicyVersion;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifierPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVerifier(): User
    {
        $verifier = User::factory()->create();
        Role::findOrCreate(Roles::VERIFIER_SERVICE, config('auth.defaults.guard'));
        $verifier->assignRole(Roles::VERIFIER_SERVICE);
        Sanctum::actingAs($verifier);

        return $verifier;
    }

    public function test_policy_endpoint_requires_verifier_role(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.verifier.policy'))
            ->assertForbidden();
    }

    public function test_policy_endpoint_returns_structure(): void
    {
        $this->actingAsVerifier();

        EngineSetting::query()->update([
            'engine_paused' => true,
        ]);

        $this->getJson(route('api.verifier.policy'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'contract_version',
                    'engine_paused',
                    'enhanced_mode_enabled',
                    'provider_policies',
                    'policies' => [
                        'standard' => [
                            'mode',
                            'enabled',
                            'dns_timeout_ms',
                            'smtp_connect_timeout_ms',
                            'smtp_read_timeout_ms',
                            'max_mx_attempts',
                            'max_concurrency_default',
                            'per_domain_concurrency',
                            'global_connects_per_minute',
                            'tempfail_backoff_seconds',
                            'circuit_breaker_tempfail_rate',
                        ],
                        'enhanced' => [
                            'mode',
                            'enabled',
                            'dns_timeout_ms',
                            'smtp_connect_timeout_ms',
                            'smtp_read_timeout_ms',
                            'max_mx_attempts',
                            'max_concurrency_default',
                            'per_domain_concurrency',
                            'global_connects_per_minute',
                            'tempfail_backoff_seconds',
                            'circuit_breaker_tempfail_rate',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'engine_paused' => true,
            ]);
    }

    public function test_policy_version_payload_endpoint_returns_payload_for_verifier_service(): void
    {
        $this->actingAsVerifier();

        SmtpPolicyVersion::query()->create([
            'version' => 'v2.9.0',
            'schema_version' => 'v2',
            'status' => 'active',
            'is_active' => true,
            'validation_status' => 'valid',
            'policy_payload' => [
                'enabled' => true,
                'version' => 'v2.9.0',
                'profiles' => [
                    'generic' => [
                        'name' => 'generic',
                        'enhanced_rules' => [],
                        'smtp_code_rules' => [],
                        'message_rules' => [],
                        'retry' => [
                            'default_seconds' => 60,
                            'tempfail_seconds' => 90,
                            'greylist_seconds' => 180,
                            'policy_blocked_seconds' => 300,
                            'unknown_seconds' => 75,
                        ],
                    ],
                ],
            ],
        ]);

        $this->getJson(route('api.verifier.policy-versions.payload', ['version' => 'v2.9.0']))
            ->assertOk()
            ->assertJsonPath('data.version', 'v2.9.0')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.validation_status', 'valid')
            ->assertJsonPath('data.schema_version', 'v2')
            ->assertJsonPath('data.policy_payload.version', 'v2.9.0');
    }

    public function test_policy_version_payload_endpoint_returns_404_when_missing_or_invalid_payload(): void
    {
        $this->actingAsVerifier();

        SmtpPolicyVersion::query()->create([
            'version' => 'v2-empty',
            'status' => 'draft',
            'is_active' => false,
            'policy_payload' => [],
        ]);

        $this->getJson(route('api.verifier.policy-versions.payload', ['version' => 'v2-empty']))
            ->assertNotFound();

        $this->getJson(route('api.verifier.policy-versions.payload', ['version' => 'v2-missing']))
            ->assertNotFound();
    }

    public function test_policy_version_payload_endpoint_rejects_invalid_validation_status(): void
    {
        $this->actingAsVerifier();

        SmtpPolicyVersion::query()->create([
            'version' => 'v2-invalid',
            'status' => 'draft',
            'validation_status' => 'invalid',
            'is_active' => false,
            'policy_payload' => [
                'enabled' => true,
                'version' => 'v2-invalid',
                'profiles' => [
                    'generic' => [
                        'name' => 'generic',
                        'retry' => [
                            'default_seconds' => 60,
                            'tempfail_seconds' => 90,
                            'greylist_seconds' => 180,
                            'policy_blocked_seconds' => 300,
                            'unknown_seconds' => 75,
                        ],
                    ],
                ],
            ],
        ]);

        $this->getJson(route('api.verifier.policy-versions.payload', ['version' => 'v2-invalid']))
            ->assertNotFound();
    }
}
