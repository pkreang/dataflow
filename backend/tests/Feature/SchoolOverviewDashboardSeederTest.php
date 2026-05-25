<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage carried over from the now-removed HomeDashboardKpiTest. The legacy
 * KPI endpoint tests went away with Phase 5 cleanup, but the school overview
 * dashboard seeder is still invoked from the school vertical demo seeders
 * (DevelopmentDemoSeeder, BodindechaDemoSeeder) and worth keeping a smoke
 * test on.
 */
class SchoolOverviewDashboardSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_seeder_creates_school_overview(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\DashboardSeeder::class);

        $this->assertDatabaseHas('report_dashboards', ['name' => 'School eForm Overview']);
    }

    public function test_school_overview_dashboard_widgets(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\DashboardSeeder::class);

        $dashboard = \App\Models\ReportDashboard::where('name', 'School eForm Overview')->first();
        $this->assertNotNull($dashboard);
        $dashboard->load('widgets');
        $this->assertCount(2, $dashboard->widgets);
        $sources = $dashboard->widgets->pluck('data_source')->sort()->values()->all();
        $this->assertSame(['school_eforms', 'school_eforms_pending'], $sources);
    }
}
