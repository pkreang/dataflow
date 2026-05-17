<?php

namespace Tests\Feature\Settings;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class EquipmentLocationsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_location(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.equipment-locations.store'), [
            'name' => 'Lab A',
            'code' => 'lab-a',
            'building' => 'Block 1',
            'floor' => '2',
            'zone' => 'East',
            'is_active' => 1,
        ])->assertRedirect(route('settings.equipment-locations.index'));

        $loc = EquipmentLocation::firstWhere('code', 'LAB-A');
        $this->assertNotNull($loc);
        $this->assertSame('Lab A', $loc->name);
        $this->assertSame('Block 1', $loc->building);
    }

    public function test_super_admin_can_update_location(): void
    {
        $admin = $this->makeSuperAdmin();
        $loc = EquipmentLocation::create([
            'name' => 'Old',
            'code' => 'OLD',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.equipment-locations.update', $loc), [
            'name' => 'Renamed',
            'code' => 'OLD',
            'building' => 'B',
            'is_active' => 1,
        ])->assertRedirect(route('settings.equipment-locations.index'));

        $this->assertSame('Renamed', $loc->fresh()->name);
    }

    public function test_super_admin_can_destroy_unused_location(): void
    {
        $admin = $this->makeSuperAdmin();
        $loc = EquipmentLocation::create([
            'name' => 'Removable',
            'code' => 'RM',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.equipment-locations.destroy', $loc))
            ->assertRedirect(route('settings.equipment-locations.index'));

        $this->assertNull($loc->fresh());
    }

    public function test_location_with_equipment_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $cat = EquipmentCategory::create(['name' => 'C', 'code' => 'C', 'is_active' => true]);
        $loc = EquipmentLocation::create([
            'name' => 'InUse',
            'code' => 'INU',
            'is_active' => true,
        ]);
        Equipment::create([
            'name' => 'E1',
            'code' => 'E1',
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
            'status' => 'active',
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.equipment-locations.destroy', $loc))
            ->assertRedirect(route('settings.equipment-locations.index'));

        $this->assertNotNull($loc->fresh());
    }
}
