<?php

namespace Tests\Feature;

use App\Models\EngineSetting;
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
}
