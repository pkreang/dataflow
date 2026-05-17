<?php

namespace Tests\Feature\Settings;

use App\Models\Department;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class DepartmentsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_department(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.departments.store'), [
            'name' => 'Engineering',
            'code' => 'eng',
            'description' => 'R&D dept',
            'is_active' => 1,
        ]);

        $department = Department::where('code', 'ENG')->firstOrFail();
        $response->assertRedirect(route('settings.departments.edit', $department));
        $this->assertSame('Engineering', $department->name);
        $this->assertTrue($department->is_active);
    }

    public function test_super_admin_can_update_department(): void
    {
        $admin = $this->makeSuperAdmin();
        $department = Department::create([
            'name' => 'Old name',
            'code' => 'OLD',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.departments.update', $department), [
            'name' => 'New name',
            'code' => 'OLD',
            'description' => 'updated',
            'is_active' => 0,
        ])->assertRedirect(route('settings.departments.edit', $department));

        $department->refresh();
        $this->assertSame('New name', $department->name);
        $this->assertSame('updated', $department->description);
        $this->assertFalse($department->is_active);
    }

    public function test_super_admin_can_destroy_unused_department(): void
    {
        $admin = $this->makeSuperAdmin();
        $department = Department::create([
            'name' => 'Removable',
            'code' => 'RM',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.departments.destroy', $department))
            ->assertRedirect(route('settings.departments.index'));

        $this->assertNull($department->fresh());
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        Department::create([
            'name' => 'Existing',
            'code' => 'DUP',
            'is_active' => true,
        ]);

        $response = $this->actingAsWebSession($admin)->post(route('settings.departments.store'), [
            'name' => 'Another',
            'code' => 'DUP',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_validation_rejects_empty_name(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.departments.store'), [
            'code' => 'X',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
