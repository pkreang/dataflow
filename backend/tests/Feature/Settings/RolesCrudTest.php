<?php

namespace Tests\Feature\Settings;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class RolesCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_role(): void
    {
        $admin = $this->makeSuperAdmin();
        $perm = Permission::first();

        $this->actingAsWebSession($admin)->post(route('roles.store'), [
            'name' => 'auditor',
            'permissions' => [$perm->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::firstWhere('name', 'auditor');
        $this->assertNotNull($role);
        $this->assertTrue($role->permissions->contains('id', $perm->id));
    }

    public function test_duplicate_role_name_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        Role::create(['name' => 'duplicate', 'guard_name' => 'web']);

        $this->actingAsWebSession($admin)->post(route('roles.store'), [
            'name' => 'duplicate',
        ])->assertSessionHasErrors('name');
    }

    public function test_super_admin_can_update_role_permissions(): void
    {
        $admin = $this->makeSuperAdmin();
        $role = Role::create(['name' => 'editor-role', 'guard_name' => 'web']);
        $perm = Permission::first();

        $this->actingAsWebSession($admin)->put(route('roles.update', $role), [
            'name' => 'editor-role',
            'permissions' => [$perm->id],
        ])->assertRedirect(route('roles.index'));

        $this->assertTrue($role->fresh()->permissions->contains('id', $perm->id));
    }

    public function test_super_admin_can_destroy_role(): void
    {
        $admin = $this->makeSuperAdmin();
        $role = Role::create(['name' => 'goner', 'guard_name' => 'web']);

        $this->actingAsWebSession($admin)->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertNull(Role::find($role->id));
    }
}
