<?php

namespace Tests\Feature\Settings;

use App\Models\NavigationMenu;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class NavigationMenusCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_menu(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.navigation.store'), [
            'label_en' => 'Reports',
            'label_th' => 'รายงาน',
            'icon' => 'chart-bar',
            'route' => '/reports',
            'sort_order' => 50,
            'is_active' => 1,
        ])->assertRedirect(route('settings.navigation.index'));

        $menu = NavigationMenu::firstWhere('label_en', 'Reports');
        $this->assertNotNull($menu);
        $this->assertSame('/reports', $menu->route);
    }

    public function test_super_admin_can_update_menu(): void
    {
        $admin = $this->makeSuperAdmin();
        $menu = NavigationMenu::create([
            'label' => 'Old',
            'label_en' => 'Old',
            'label_th' => 'เก่า',
            'route' => '/old',
            'sort_order' => 100,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.navigation.update', $menu), [
            'label_en' => 'New',
            'label_th' => 'ใหม่',
            'route' => '/new',
            'parent_id' => null,
            'sort_order' => 100,
            'is_active' => 1,
        ])->assertRedirect(route('settings.navigation.index'));

        $menu->refresh();
        $this->assertSame('New', $menu->label_en);
        $this->assertSame('/new', $menu->route);
    }

    public function test_super_admin_can_destroy_leaf_menu(): void
    {
        $admin = $this->makeSuperAdmin();
        $menu = NavigationMenu::create([
            'label' => 'Leaf',
            'label_en' => 'Leaf',
            'label_th' => 'ใบ',
            'route' => '/leaf',
            'sort_order' => 100,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.navigation.destroy', $menu))
            ->assertRedirect(route('settings.navigation.index'));

        $this->assertNull($menu->fresh());
    }

    public function test_menu_with_children_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $parent = NavigationMenu::create([
            'label' => 'Parent',
            'label_en' => 'Parent',
            'label_th' => 'พ่อ',
            'sort_order' => 100,
            'is_active' => true,
        ]);
        NavigationMenu::create([
            'label' => 'Child',
            'label_en' => 'Child',
            'label_th' => 'ลูก',
            'parent_id' => $parent->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.navigation.destroy', $parent))
            ->assertRedirect(route('settings.navigation.index'));

        $this->assertNotNull($parent->fresh());
    }

    public function test_toggle_flips_active_flag_and_clears_cache(): void
    {
        $admin = $this->makeSuperAdmin();
        $menu = NavigationMenu::create([
            'label' => 'Toggle',
            'label_en' => 'Toggle',
            'label_th' => 'สลับ',
            'sort_order' => 100,
            'is_active' => true,
        ]);
        Cache::put('navigation_menus_tree', 'cached', 60);

        $this->actingAsWebSession($admin)->patch(route('settings.navigation.toggle', $menu))
            ->assertOk()
            ->assertJson(['is_active' => false]);

        $this->assertFalse($menu->fresh()->is_active);
        $this->assertFalse(Cache::has('navigation_menus_tree'));
    }

    public function test_reorder_updates_sort_order_within_parent_group(): void
    {
        $admin = $this->makeSuperAdmin();
        $a = NavigationMenu::create(['label' => 'A', 'label_en' => 'A', 'label_th' => 'A', 'sort_order' => 1, 'is_active' => true]);
        $b = NavigationMenu::create(['label' => 'B', 'label_en' => 'B', 'label_th' => 'B', 'sort_order' => 2, 'is_active' => true]);

        $this->actingAsWebSession($admin)->patch(route('settings.navigation.reorder'), [
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }
}
