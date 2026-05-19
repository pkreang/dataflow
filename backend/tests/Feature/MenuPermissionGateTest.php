<?php

namespace Tests\Feature;

use App\Models\NavigationMenu;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * EnforceMenuPermission middleware — a navigation menu's `permission` gates the
 * underlying route, not just the sidebar entry.
 */
class MenuPermissionGateTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    /** Attach a permission to the /dashboard route via a navigation menu row. */
    private function gateDashboard(string $permission): void
    {
        NavigationMenu::create([
            'label' => 'Gated Dashboard',
            'icon' => 'home',
            'route' => '/dashboard',
            'permission' => $permission,
            'sort_order' => 999,
            'is_active' => true,
        ]);
    }

    public function test_route_is_forbidden_when_user_lacks_the_menu_permission(): void
    {
        $this->gateDashboard('dashboard.read');

        $this->actingAsWebSession($this->makeRegularUser())
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_route_is_allowed_when_user_has_the_menu_permission(): void
    {
        $this->gateDashboard('dashboard.read');

        $user = $this->makeRegularUser();
        $user->givePermissionTo('dashboard.read');

        $this->actingAsWebSession($user)
            ->get('/dashboard')
            ->assertSuccessful();
    }

    public function test_super_admin_bypasses_the_menu_permission_gate(): void
    {
        $this->gateDashboard('dashboard.read');

        $this->actingAsWebSession($this->makeSuperAdmin())
            ->get('/dashboard')
            ->assertSuccessful();
    }

    public function test_route_without_a_permissioned_menu_is_not_gated(): void
    {
        // No gating menu created — the seeded /dashboard menu has a null permission.
        $this->actingAsWebSession($this->makeRegularUser())
            ->get('/dashboard')
            ->assertSuccessful();
    }
}
