<?php

namespace Tests\Feature\Settings;

use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class UsersCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_user_with_default_role(): void
    {
        $admin = $this->makeSuperAdmin();
        $position = Position::create(['name' => 'P', 'code' => 'P', 'is_active' => true]);
        $role = Role::firstWhere('name', 'admin') ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $response = $this->actingAsWebSession($admin)->post(route('users.store'), [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'new-hire@example.test',
            'position_id' => $position->id,
            'role_type' => 'default',
            'role_id' => $role->id,
        ]);

        $user = User::firstWhere('email', 'new-hire@example.test');
        $this->assertNotNull($user);
        // Redirect lands on the new user's edit page so admin can pick how to set password.
        $response->assertRedirect(route('users.edit', ['user' => $user->id, 'just_created' => 1]));
        $this->assertTrue($user->hasRole($role->name));
    }

    public function test_super_admin_can_assign_org_unit_on_create(): void
    {
        $admin = $this->makeSuperAdmin();
        $position = Position::create(['name' => 'P', 'code' => 'P', 'is_active' => true]);
        $org = OrgUnit::create(['name' => 'Engineering', 'type' => 'department', 'is_active' => true]);
        $role = Role::firstWhere('name', 'admin') ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->actingAsWebSession($admin)->post(route('users.store'), [
            'first_name' => 'Org', 'last_name' => 'Member',
            'email' => 'org-member@example.test',
            'org_unit_id' => $org->id,
            'position_id' => $position->id,
            'role_type' => 'default', 'role_id' => $role->id,
        ])->assertRedirect();

        $user = User::firstWhere('email', 'org-member@example.test');
        $this->assertSame($org->id, (int) $user->org_unit_id);
    }

    public function test_validation_rejects_missing_fields(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('users.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'position_id']);
    }

    public function test_duplicate_email_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        User::create([
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'dup@example.test',
            'password' => 'pw',
            'is_active' => true,
        ]);
        $position = Position::create(['name' => 'P', 'code' => 'P', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('users.store'), [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'email' => 'dup@example.test',
            'position_id' => $position->id,
            'role_type' => 'custom',
            'permissions' => [],
        ])->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_destroy_user(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::create([
            'first_name' => 'Goner',
            'last_name' => 'Bye',
            'email' => 'goner@example.test',
            'password' => 'pw',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('users.destroy', $user))
            ->assertRedirect();

        $this->assertNull(User::find($user->id));
    }

    public function test_regular_user_cannot_reach_user_create(): void
    {
        $this->actingAsWebSession($this->makeRegularUser())
            ->get(route('users.create'))
            ->assertForbidden();
    }
}
