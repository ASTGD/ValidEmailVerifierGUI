<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EngineServerFallbackUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_ui_is_hidden_when_disabled(): void
    {
        config()->set('engine_servers.fallback_ui_enabled', false);
        config()->set('engine_servers.fallback_ui_super_admin_only', true);
        config()->set('engine_servers.fallback_ui_super_admin_emails', ['ceo@example.com']);

        $admin = $this->makeAdmin('ops@example.com');

        $this->actingAs($admin)
            ->get('/admin/engine-servers')
            ->assertForbidden();
    }

    public function test_fallback_ui_is_visible_only_to_allowlisted_super_admins_when_enabled(): void
    {
        config()->set('engine_servers.fallback_ui_enabled', true);
        config()->set('engine_servers.fallback_ui_super_admin_only', true);
        config()->set('engine_servers.fallback_ui_super_admin_emails', ['ceo@example.com']);

        $admin = $this->makeAdmin('ops@example.com');
        $superAdmin = $this->makeAdmin('ceo@example.com');

        $this->actingAs($admin)
            ->get('/admin/engine-servers')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/engine-servers')
            ->assertOk();
    }

    public function test_fallback_ui_allows_admin_when_super_admin_gate_disabled(): void
    {
        config()->set('engine_servers.fallback_ui_enabled', true);
        config()->set('engine_servers.fallback_ui_super_admin_only', false);
        config()->set('engine_servers.fallback_ui_super_admin_emails', []);

        $admin = $this->makeAdmin('ops@example.com');

        $this->actingAs($admin)
            ->get('/admin/engine-servers')
            ->assertOk();
    }

    private function makeAdmin(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
        ]);
        Role::findOrCreate(Roles::ADMIN, 'web');
        $user->assignRole(Roles::ADMIN);

        return $user;
    }
}
