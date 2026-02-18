<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InternalDocsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_docs_portal_index_and_allowlisted_page(): void
    {
        $admin = $this->makeAdmin();

        $indexResponse = $this->actingAs($admin)->get(route('internal.docs.index'));
        $indexResponse->assertRedirect(route('internal.docs.page', ['section' => 'platform', 'page' => 'overview']));

        $pageResponse = $this->actingAs($admin)
            ->get(route('internal.docs.page', ['section' => 'go', 'page' => 'runtime-settings']));

        $pageResponse
            ->assertOk()
            ->assertSee('Go Runtime Settings Reference')
            ->assertSee('alert_error_rate_threshold')
            ->assertSee('<h1', false)
            ->assertSee('<h2', false)
            ->assertSee('<table', false)
            ->assertSee('<thead', false)
            ->assertSee('<tbody', false);
    }

    public function test_non_admin_cannot_access_internal_docs_portal(): void
    {
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $customer = User::factory()->create();
        $customer->assignRole(Roles::CUSTOMER);

        $response = $this->actingAs($customer)->get(route('internal.docs.index'));
        $response->assertForbidden();
    }

    public function test_unknown_docs_slug_returns_not_found(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->get(route('internal.docs.page', ['section' => 'unknown', 'page' => 'missing']));

        $response->assertNotFound();
    }

    private function makeAdmin(): User
    {
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));

        $admin = User::factory()->create();
        $admin->assignRole(Roles::ADMIN);

        return $admin;
    }
}
