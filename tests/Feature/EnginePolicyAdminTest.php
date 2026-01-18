<?php

namespace Tests\Feature;

use App\Enums\VerificationMode;
use App\Filament\Resources\EngineVerificationPolicies\Pages\EditEngineVerificationPolicy;
use App\Models\EngineVerificationPolicy;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnginePolicyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));
        $admin->assignRole(Roles::ADMIN);

        return $admin;
    }

    public function test_admin_can_update_verification_policy(): void
    {
        $admin = $this->makeAdmin();

        $policy = EngineVerificationPolicy::query()
            ->where('mode', VerificationMode::Standard->value)
            ->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditEngineVerificationPolicy::class, ['record' => $policy->id])
            ->fillForm([
                'mode' => VerificationMode::Standard->value,
                'enabled' => true,
                'dns_timeout_ms' => 2500,
                'smtp_connect_timeout_ms' => 2000,
                'smtp_read_timeout_ms' => 2000,
                'max_mx_attempts' => 2,
                'max_concurrency_default' => 3,
                'per_domain_concurrency' => 2,
                'global_connects_per_minute' => null,
                'tempfail_backoff_seconds' => null,
                'circuit_breaker_tempfail_rate' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $policy->refresh();
        $this->assertSame(2500, $policy->dns_timeout_ms);
        $this->assertSame(3, $policy->max_concurrency_default);
    }
}
