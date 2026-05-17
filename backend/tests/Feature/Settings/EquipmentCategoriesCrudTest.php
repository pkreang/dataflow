<?php

namespace Tests\Feature\Settings;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class EquipmentCategoriesCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_category(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.equipment.store'), [
            'name' => 'HVAC',
            'code' => 'hvac',
            'description' => 'Air conditioning',
            'is_active' => 1,
        ])->assertRedirect(route('settings.equipment.index'));

        $cat = EquipmentCategory::firstWhere('code', 'HVAC');
        $this->assertNotNull($cat);
        $this->assertSame('HVAC', $cat->name);
    }

    public function test_super_admin_can_update_category(): void
    {
        $admin = $this->makeSuperAdmin();
        $cat = EquipmentCategory::create([
            'name' => 'Old',
            'code' => 'OLD',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.equipment.update', $cat), [
            'name' => 'Renamed',
            'code' => 'OLD',
            'is_active' => 0,
        ])->assertRedirect(route('settings.equipment.index'));

        $cat->refresh();
        $this->assertSame('Renamed', $cat->name);
        $this->assertFalse($cat->is_active);
    }

    public function test_super_admin_can_destroy_unused_category(): void
    {
        $admin = $this->makeSuperAdmin();
        $cat = EquipmentCategory::create([
            'name' => 'Removable',
            'code' => 'RM',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.equipment.destroy', $cat))
            ->assertRedirect(route('settings.equipment.index'));

        $this->assertNull($cat->fresh());
    }

    public function test_category_with_equipment_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $cat = EquipmentCategory::create([
            'name' => 'InUse',
            'code' => 'INU',
            'is_active' => true,
        ]);
        $loc = \App\Models\EquipmentLocation::create(['name' => 'L', 'code' => 'L', 'is_active' => true]);
        Equipment::create([
            'name' => 'AC-1',
            'code' => 'AC-1',
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
            'status' => 'active',
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.equipment.destroy', $cat))
            ->assertRedirect(route('settings.equipment.index'));

        $this->assertNotNull($cat->fresh());
    }

    public function test_duplicate_code_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        EquipmentCategory::create([
            'name' => 'X',
            'code' => 'DUP',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.equipment.store'), [
            'name' => 'Y',
            'code' => 'DUP',
        ])->assertSessionHasErrors('code');
    }
}
