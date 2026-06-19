<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\OrgUnit;
use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\User;
use App\Support\DataSourceRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Org-model consolidation Phase 2d — reports/dashboard filter by org_unit_id.
 * ดู doc/org-model-consolidation-spec.md
 */
class ReportOrgUnitFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_exposes_org_unit_filter_for_instance_sources(): void
    {
        $source = DataSourceRegistry::get('repair_requests');
        $this->assertArrayHasKey('org_unit_id', $source['filter_fields']);
        $this->assertArrayHasKey('org_unit_id', $source['group_by_fields']);
    }

    public function test_widget_data_filters_by_org_unit_id(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $user = User::create([
            'first_name' => 'API', 'last_name' => 'User', 'email' => 'rpt-org@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => true,
        ]);
        $token = $user->createToken('t')->plainTextToken;

        $dashboard = ReportDashboard::create([
            'name' => 'D', 'layout_columns' => 1, 'visibility' => 'all', 'is_active' => true,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id, 'title' => 'Total', 'widget_type' => 'metric',
            'data_source' => 'repair_requests', 'config' => ['aggregation' => 'count', 'field' => 'id'],
            'col_span' => 1, 'sort_order' => 1,
        ]);

        $orgA = OrgUnit::create(['name' => 'Org A', 'type' => 'department', 'is_active' => true]);
        $orgB = OrgUnit::create(['name' => 'Org B', 'type' => 'department', 'is_active' => true]);

        $workflowId = DB::table('approval_workflows')->insertGetId([
            'name' => 'WF', 'document_type' => 'repair_request', 'is_active' => true,
            'allow_requester_as_approver' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$orgA->id, 'RR-A'], [$orgA->id, 'RR-B'], [$orgB->id, 'RR-C']] as [$orgId, $ref]) {
            ApprovalInstance::create([
                'workflow_id' => $workflowId, 'document_type' => 'repair_request',
                'reference_no' => $ref, 'status' => 'pending', 'current_step_no' => 1,
                'requester_user_id' => 1, 'org_unit_id' => $orgId,
            ]);
        }

        $base = "/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/data";

        $this->withToken($token)->getJson($base)
            ->assertOk()->assertJson(['value' => 3]);

        $this->withToken($token)->getJson($base.'?org_unit_id='.$orgA->id)
            ->assertOk()->assertJson(['value' => 2]);

        $this->withToken($token)->getJson($base.'?org_unit_id='.$orgB->id)
            ->assertOk()->assertJson(['value' => 1]);
    }
}
