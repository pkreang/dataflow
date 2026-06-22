<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Monthly group-by ("created_at:month") for chart widgets — time-series line/area.
 * Buckets a date column into YYYY-MM (driver-agnostic) and orders chronologically.
 */
class DashboardMonthlyChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_group_by_buckets_by_year_month_in_chronological_order(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $token = $this->superAdminToken();
        [$dashboard, $widget] = $this->makeMonthlyChartWidget();

        // 2 in Jan, 3 in Feb, 1 in Mar — seeded out of order on purpose
        $this->makeRepair(Carbon::create(2025, 2, 10, 9));
        $this->makeRepair(Carbon::create(2025, 1, 15, 9));
        $this->makeRepair(Carbon::create(2025, 3, 5, 9));
        $this->makeRepair(Carbon::create(2025, 2, 20, 9));
        $this->makeRepair(Carbon::create(2025, 1, 28, 9));
        $this->makeRepair(Carbon::create(2025, 2, 2, 9));

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/data");

        $response->assertOk();
        $response->assertJson([
            'labels' => ['2025-01', '2025-02', '2025-03'],
            'datasets' => [['data' => [2, 3, 1]]],
        ]);
    }

    public function test_month_group_by_rejects_non_date_base_column(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $token = $this->superAdminToken();
        // status is not a date_field → "status:month" must resolve to empty, not error
        [$dashboard, $widget] = $this->makeMonthlyChartWidget(groupBy: 'status:month');
        $this->makeRepair(Carbon::create(2025, 1, 15, 9));

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/data");

        $response->assertOk();
        $response->assertJson(['labels' => [], 'datasets' => [['data' => []]]]);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function superAdminToken(): string
    {
        $user = User::create([
            'first_name' => 'API',
            'last_name' => 'User',
            'email' => 'api-month@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        return $user->createToken('test-month')->plainTextToken;
    }

    private function makeMonthlyChartWidget(string $groupBy = 'created_at:month'): array
    {
        $dashboard = ReportDashboard::create([
            'name' => 'Monthly Trend',
            'layout_columns' => 1,
            'visibility' => 'all',
            'is_active' => true,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'Repairs per month',
            'widget_type' => 'chart',
            'data_source' => 'repair_requests',
            'config' => [
                'chart_type' => 'line',
                'aggregation' => 'count',
                'field' => 'id',
                'group_by' => $groupBy,
            ],
            'col_span' => 3,
            'sort_order' => 1,
        ]);

        return [$dashboard, $widget];
    }

    private function makeRepair(Carbon $when): void
    {
        $workflowId = DB::table('approval_workflows')
            ->where('document_type', 'repair_request')
            ->value('id')
            ?? DB::table('approval_workflows')->insertGetId([
                'name' => 'Test Workflow',
                'document_type' => 'repair_request',
                'description' => null,
                'is_active' => true,
                'allow_requester_as_approver' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $inst = new ApprovalInstance([
            'workflow_id' => $workflowId,
            'document_type' => 'repair_request',
            'reference_no' => 'RR-'.$when->format('YmdHis'),
            'status' => 'approved',
            'current_step_no' => 1,
            'requester_user_id' => 1,
        ]);
        $inst->created_at = $when;
        $inst->updated_at = $when;
        $inst->save();
    }
}
