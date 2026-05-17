<?php

namespace Tests\Feature;

use App\Models\ReportDashboard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Self-service report builder (Tier A MVP) — ensures regular users can manage
 * their own dashboards at /my-reports, cannot touch other users' dashboards,
 * and that dashboards persist with the owner-only sentinel.
 */
class MyReportControllerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_index_only_shows_dashboards_owned_by_current_user(): void
    {
        $owner = $this->makeRegularUser('owner@example.test');
        $other = $this->makeRegularUser('other@example.test');

        $ownerDashboard = ReportDashboard::create([
            'name' => 'My Sales',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $otherDashboard = ReportDashboard::create([
            'name' => 'Other Sales',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $other->id,
        ]);

        $response = $this->actingAsWebSession($owner)->get(route('my-reports.index'));

        $response->assertSuccessful();
        $response->assertSee('My Sales');
        $response->assertDontSee('Other Sales');
    }

    public function test_store_creates_dashboard_scoped_to_current_user(): void
    {
        $user = $this->makeRegularUser();

        $payload = [
            'name' => 'Repair Volume',
            'description' => 'Monthly repair count',
            'layout_columns' => 2,
            'is_active' => true,
            'widgets' => [
                [
                    'title' => 'Total Open',
                    'widget_type' => 'metric',
                    'data_source' => 'repair_requests',
                    'aggregation' => 'count',
                    'config_field' => 'id',
                    'col_span' => 1,
                ],
            ],
        ];

        $this->actingAsWebSession($user)
            ->post(route('my-reports.store'), $payload)
            ->assertRedirect(route('my-reports.index'));

        $this->assertDatabaseHas('report_dashboards', [
            'name' => 'Repair Volume',
            'created_by' => $user->id,
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
        ]);
    }

    public function test_owner_can_load_edit_page(): void
    {
        $owner = $this->makeRegularUser();

        $dashboard = ReportDashboard::create([
            'name' => 'My Editable',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAsWebSession($owner)
            ->get(route('my-reports.edit', $dashboard))
            ->assertSuccessful()
            ->assertSee('My Editable');
    }

    public function test_edit_404s_when_user_does_not_own_dashboard(): void
    {
        $owner = $this->makeRegularUser('owner@example.test');
        $other = $this->makeRegularUser('other@example.test');

        $dashboard = ReportDashboard::create([
            'name' => 'Sales by Region',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAsWebSession($other)
            ->get(route('my-reports.edit', $dashboard))
            ->assertNotFound();
    }

    public function test_destroy_404s_when_user_does_not_own_dashboard(): void
    {
        $owner = $this->makeRegularUser('owner@example.test');
        $other = $this->makeRegularUser('other@example.test');

        $dashboard = ReportDashboard::create([
            'name' => 'Sales by Region',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAsWebSession($other)
            ->delete(route('my-reports.destroy', $dashboard))
            ->assertNotFound();

        $this->assertDatabaseHas('report_dashboards', ['id' => $dashboard->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('my-reports.index'))->assertRedirect('/login');
        $this->get(route('my-reports.create'))->assertRedirect('/login');
    }

    public function test_owner_can_view_own_dashboard_via_reports_show(): void
    {
        $owner = $this->makeRegularUser();

        $dashboard = ReportDashboard::create([
            'name' => 'My View',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAsWebSession($owner)
            ->get(route('reports.dashboards.show', $dashboard))
            ->assertSuccessful();
    }

    public function test_non_owner_403s_on_owner_only_dashboard(): void
    {
        $owner = $this->makeRegularUser('owner@example.test');
        $other = $this->makeRegularUser('other@example.test');

        $dashboard = ReportDashboard::create([
            'name' => 'Private',
            'visibility' => 'permission',
            'required_permission' => '__owner_only__',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAsWebSession($other)
            ->get(route('reports.dashboards.show', $dashboard))
            ->assertForbidden();
    }
}
