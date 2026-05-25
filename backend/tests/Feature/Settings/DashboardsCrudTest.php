<?php

namespace Tests\Feature\Settings;

use App\Models\ReportDashboard;
use App\Support\DataSourceRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class DashboardsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_dashboard_with_metric_widget(): void
    {
        $admin = $this->makeSuperAdmin();
        $source = DataSourceRegistry::sourceKeys()[0];

        $this->actingAsWebSession($admin)->post(route('settings.dashboards.store'), [
            'name' => 'Ops overview',
            'description' => 'Top metrics',
            'layout_columns' => 2,
            'visibility' => 'all',
            'is_active' => 1,
            'widgets' => [
                [
                    'title' => 'Total',
                    'widget_type' => 'metric',
                    'data_source' => $source,
                    'aggregation' => 'count',
                    'config_field' => 'id',
                    'col_span' => 1,
                ],
            ],
        ])->assertRedirect(route('settings.dashboards.index'));

        $dash = ReportDashboard::firstWhere('name', 'Ops overview');
        $this->assertNotNull($dash);
        $this->assertSame(1, $dash->widgets()->count());
    }

    public function test_super_admin_can_destroy_dashboard(): void
    {
        $admin = $this->makeSuperAdmin();
        $dash = ReportDashboard::create([
            'name' => 'Goner',
            'visibility' => 'all',
            'is_active' => true,
            'layout_columns' => 2,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.dashboards.destroy', $dash))
            ->assertRedirect(route('settings.dashboards.index'));

        $this->assertNull($dash->fresh());
    }

    public function test_validation_rejects_missing_widgets(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.dashboards.store'), [
            'name' => 'No widgets',
            'visibility' => 'all',
        ])->assertSessionHasErrors('widgets');
    }

    public function test_permission_visibility_requires_permission_name(): void
    {
        $admin = $this->makeSuperAdmin();
        $source = DataSourceRegistry::sourceKeys()[0];

        $this->actingAsWebSession($admin)->post(route('settings.dashboards.store'), [
            'name' => 'Permissioned',
            'visibility' => 'permission',
            'widgets' => [
                ['title' => 'X', 'widget_type' => 'metric', 'data_source' => $source],
            ],
        ])->assertSessionHasErrors('required_permission');
    }
}
