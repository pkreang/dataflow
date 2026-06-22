<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsMenuAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function superAdminOnlyRoutes(): array
    {
        return [
            'branding' => ['settings.branding'],
            'positions index' => ['settings.positions.index'],
            'workflow index' => ['settings.workflow.index'],
            'approval routing' => ['settings.approval-routing'],
            'system change log' => ['settings.system-change-log'],
            'authentication' => ['settings.auth'],
            'document types index' => ['settings.document-types.index'],
            'lookups index' => ['settings.lookups.index'],
            'document forms index' => ['settings.document-forms.index'],
            'notifications index' => ['settings.notifications.index'],
            'branch scoping' => ['settings.branch-scoping'],
            'running numbers index' => ['settings.running-numbers.index'],
            'activity history' => ['settings.activity-history.index'],
            'navigation index' => ['settings.navigation.index'],
            'dashboards index' => ['settings.dashboards.index'],
            // Settings list pages gated by their navigation menu's permission via
            // the EnforceMenuPermission middleware — a bare regular user (no
            // permissions) is forbidden, a super-admin bypasses. (The 19 above
            // are gated by the super-admin middleware; same observable result.)
            'organizations index' => ['companies.index'],
            'users index' => ['users.index'],
            'roles index' => ['roles.index'],
            'permissions index' => ['permissions.index'],
            'password policy' => ['settings.password-policy'],
        ];
    }

    #[DataProvider('superAdminOnlyRoutes')]
    public function test_super_admin_route_redirects_guest_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect('/login');
    }

    #[DataProvider('superAdminOnlyRoutes')]
    public function test_super_admin_route_forbidden_for_regular_user(string $routeName): void
    {
        $response = $this->actingAsWebSession($this->makeRegularUser())->get(route($routeName));
        $response->assertForbidden();
    }

    #[DataProvider('superAdminOnlyRoutes')]
    public function test_super_admin_route_renders_for_super_admin(string $routeName): void
    {
        $response = $this->actingAsWebSession($this->makeSuperAdmin())->get(route($routeName));
        $response->assertSuccessful();
        $response->assertDontSee('Whoops, looks like something went wrong');
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'settings-smoke-admin@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function makeRegularUser(): User
    {
        return User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'settings-smoke-user@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
