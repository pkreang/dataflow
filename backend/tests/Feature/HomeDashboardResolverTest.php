<?php

namespace Tests\Feature;

use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the home-dashboard resolver replacing the old
 * hardcoded KPI grid: visibility scope, pick-fallback chain, profile picker
 * guard, and the runtime `{current_user}` filter token used by widgets.
 */
class HomeDashboardResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessible_to_scope_filters_by_visibility_and_permission(): void
    {
        $this->seedBase();
        $regular = $this->makeUser('regular@test.local');
        $approver = $this->makeUser('approver@test.local');
        $approver->givePermissionTo('approval.approve');

        $public = ReportDashboard::create([
            'name' => 'Public', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2,
        ]);
        $manager = ReportDashboard::create([
            'name' => 'Manager', 'visibility' => 'permission',
            'required_permission' => 'approval.approve',
            'is_active' => true, 'layout_columns' => 2,
        ]);

        $this->assertSame(
            ['Public'],
            ReportDashboard::accessibleTo($regular)->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(
            ['Manager', 'Public'],
            ReportDashboard::accessibleTo($approver)->orderBy('name')->pluck('name')->all()
        );

        $this->assertTrue($public->canBeAccessedBy($regular));
        $this->assertFalse($manager->canBeAccessedBy($regular));
        $this->assertTrue($manager->canBeAccessedBy($approver));
    }

    public function test_dashboard_route_renders_user_pick_when_set(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $a = ReportDashboard::create(['name' => 'Alpha', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        $b = ReportDashboard::create(['name' => 'Beta', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        $user->update(['home_dashboard_id' => $b->id]);

        $response = $this->actingAsWebSession($user)->get('/dashboard');
        $response->assertOk()->assertSee('Beta')->assertDontSee('Alpha — body');
    }

    public function test_dashboard_route_falls_back_to_setting_default_when_no_pick(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $a = ReportDashboard::create(['name' => 'Alpha', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        $b = ReportDashboard::create(['name' => 'Beta', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        Setting::set('default_home_dashboard_id', (string) $b->id);

        $response = $this->actingAsWebSession($user)->get('/dashboard');
        $response->assertOk()->assertSee('Beta');
    }

    public function test_dashboard_route_falls_back_to_first_accessible_when_pick_inaccessible(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $manager = ReportDashboard::create([
            'name' => 'Manager Only', 'visibility' => 'permission',
            'required_permission' => 'approval.approve',
            'is_active' => true, 'layout_columns' => 2,
        ]);
        $public = ReportDashboard::create(['name' => 'Public Fallback', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        // Pretend the user previously pinned the manager dashboard, then later
        // lost the permission — resolver must skip the stale pick and serve
        // the public fallback instead of returning the empty state.
        $user->update(['home_dashboard_id' => $manager->id]);

        $response = $this->actingAsWebSession($user)->get('/dashboard');
        $response->assertOk()->assertSee('Public Fallback');
    }

    public function test_dashboard_renders_empty_state_when_no_accessible_dashboards(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        // Manager-only dashboard plus no public one — user can't access any.
        ReportDashboard::create([
            'name' => 'Manager Only', 'visibility' => 'permission',
            'required_permission' => 'approval.approve',
            'is_active' => true, 'layout_columns' => 2,
        ]);

        $response = $this->actingAsWebSession($user)->get('/dashboard');
        $response->assertOk()->assertSee(__('common.no_home_dashboard_title'));
    }

    public function test_profile_picker_rejects_inaccessible_dashboard(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $manager = ReportDashboard::create([
            'name' => 'Manager Only', 'visibility' => 'permission',
            'required_permission' => 'approval.approve',
            'is_active' => true, 'layout_columns' => 2,
        ]);

        $response = $this->actingAsWebSession($user)
            ->patch('/myprofile/home-dashboard', ['home_dashboard_id' => $manager->id]);

        $response->assertSessionHasErrors('home_dashboard_id');
        $user->refresh();
        $this->assertNull($user->home_dashboard_id);
    }

    public function test_profile_picker_accepts_accessible_dashboard(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $public = ReportDashboard::create(['name' => 'Public', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);

        $this->actingAsWebSession($user)
            ->patch('/myprofile/home-dashboard', ['home_dashboard_id' => $public->id])
            ->assertRedirect();

        $this->assertSame($public->id, $user->fresh()->home_dashboard_id);
    }

    public function test_profile_picker_accepts_null_to_clear_pick(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $public = ReportDashboard::create(['name' => 'Public', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 2]);
        $user->update(['home_dashboard_id' => $public->id]);

        $this->actingAsWebSession($user)
            ->patch('/myprofile/home-dashboard', ['home_dashboard_id' => null])
            ->assertRedirect();

        $this->assertNull($user->fresh()->home_dashboard_id);
    }

    public function test_widget_filter_current_user_token_scopes_metric_per_viewer(): void
    {
        $this->seedBase();
        $alice = $this->makeUser('alice@test.local');
        $bob = $this->makeUser('bob@test.local');

        // Three submissions: 2 Alice (1 draft + 1 submitted), 1 Bob (draft).
        // Widget counts drafts owned by the requesting user, so Alice should
        // see 1 and Bob should see 1.
        $form = \App\Models\DocumentForm::create([
            'form_key' => 'demo_form',
            'name' => 'Demo Form',
            'document_type' => 'form_submission',
            'is_active' => true,
            'layout_columns' => 1,
        ]);
        \App\Models\DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $alice->id, 'status' => 'draft', 'payload' => [],
        ]);
        \App\Models\DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $alice->id, 'status' => 'submitted', 'payload' => [],
        ]);
        \App\Models\DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $bob->id, 'status' => 'draft', 'payload' => [],
        ]);

        $dashboard = ReportDashboard::create([
            'name' => 'Per-user', 'visibility' => 'all', 'is_active' => true, 'layout_columns' => 1,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'My drafts',
            'widget_type' => 'metric',
            'data_source' => 'document_form_submissions',
            'config' => [
                'aggregation' => 'count',
                'field' => 'id',
                'filters' => [
                    'user_id' => '{current_user}',
                    'status' => 'draft',
                ],
            ],
            'col_span' => 1,
            'sort_order' => 1,
        ]);

        $aliceToken = $alice->createToken('test-alice')->plainTextToken;
        $bobToken = $bob->createToken('test-bob')->plainTextToken;

        $aliceResponse = $this->withToken($aliceToken)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/data");
        $aliceResponse->assertOk()->assertJson(['value' => 1]);

        $bobResponse = $this->withToken($bobToken)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/data");
        $bobResponse->assertOk()->assertJson(['value' => 1]);
    }

    public function test_home_dashboard_seeder_creates_default_and_manager(): void
    {
        $this->seedBase();
        $this->seed(\Database\Seeders\HomeDashboardSeeder::class);

        $this->assertDatabaseHas('report_dashboards', ['name' => 'Home (Default)', 'visibility' => 'all']);
        $this->assertDatabaseHas('report_dashboards', [
            'name' => 'Home (Manager)', 'visibility' => 'permission', 'required_permission' => 'approval.approve',
        ]);
        $this->assertNotEmpty(Setting::get('default_home_dashboard_id'));
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeUser(string $email = 'user@test.local'): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'department_id' => $user->department_id,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
