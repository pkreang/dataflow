<?php

namespace Tests\Feature\Settings;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class PermissionsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_permission(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('permissions.store'), [
            'name' => 'reports.export',
        ])->assertRedirect(route('permissions.index'));

        $perm = Permission::firstWhere('name', 'reports.export');
        $this->assertNotNull($perm);
        $this->assertSame('reports', $perm->module);
        $this->assertSame('export', $perm->action);
    }

    public function test_duplicate_permission_name_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        Permission::create(['name' => 'something.read', 'guard_name' => 'web', 'module' => 'something', 'action' => 'read']);

        $this->actingAsWebSession($admin)->post(route('permissions.store'), [
            'name' => 'something.read',
        ])->assertSessionHasErrors('name');
    }

    public function test_super_admin_can_update_permission_name(): void
    {
        $admin = $this->makeSuperAdmin();
        $perm = Permission::create(['name' => 'old.action', 'guard_name' => 'web', 'module' => 'old', 'action' => 'action']);

        $this->actingAsWebSession($admin)->put(route('permissions.update', $perm), [
            'name' => 'new.action',
        ])->assertRedirect(route('permissions.index'));

        $this->assertSame('new.action', $perm->fresh()->name);
    }

    public function test_super_admin_can_destroy_unused_permission(): void
    {
        $admin = $this->makeSuperAdmin();
        $perm = Permission::create(['name' => 'temp.thing', 'guard_name' => 'web', 'module' => 'temp', 'action' => 'thing']);

        $this->actingAsWebSession($admin)->delete(route('permissions.destroy', $perm))
            ->assertRedirect(route('permissions.index'));

        $this->assertNull(Permission::find($perm->id));
    }

    public function test_permission_in_use_by_role_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $perm = Permission::create(['name' => 'in-use.thing', 'guard_name' => 'web', 'module' => 'in-use', 'action' => 'thing']);
        $role = Role::create(['name' => 'role-holder', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $this->actingAsWebSession($admin)->delete(route('permissions.destroy', $perm))
            ->assertRedirect(route('permissions.index'));

        $this->assertNotNull(Permission::find($perm->id));
    }

    public function test_regular_user_cannot_reach_permission_create(): void
    {
        $this->actingAsWebSession($this->makeRegularUser())
            ->get(route('permissions.create'))
            ->assertForbidden();
    }
}
