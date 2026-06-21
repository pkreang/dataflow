<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_widget_export_returns_csv_with_utf8_bom_and_headers(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboard, $widget] = $this->makeTableWidget();
        $this->createRepairRequests(3);

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'CSV should start with UTF-8 BOM');

        // First non-BOM line = column headers
        $lines = explode("\n", trim(substr($body, 3)));
        $this->assertStringContainsString('Ref No', $lines[0]);
        $this->assertGreaterThanOrEqual(3 + 1, count($lines)); // 3 rows + header
    }

    public function test_chart_widget_export_returns_2_column_csv(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboard, $widget] = $this->makeChartWidget();
        $this->createRepairRequests(5);

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/export");

        $response->assertOk();
        $body = $response->streamedContent();
        $lines = explode("\n", trim(substr($body, 3)));

        $this->assertSame('Status,Count', trim($lines[0]));
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    public function test_metric_widget_export_returns_422(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboard, $widget] = $this->makeMetricWidget();

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}/export");

        $response->assertStatus(422);
    }

    public function test_dashboard_export_returns_zip_with_entries(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboard, $_tableWidget] = $this->makeTableWidget();
        $this->makeChartWidget(existingDashboard: $dashboard);
        $this->makeMetricWidget(existingDashboard: $dashboard); // should be excluded from zip
        $this->createRepairRequests(2);

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/export");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        // Write zip body to a temp file and verify contents
        $body = $response->streamedContent();
        $tmp = tempnam(sys_get_temp_dir(), 'test-zip-');
        file_put_contents($tmp, $body);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmp);

        // 2 exportable widgets (table + chart). Metric excluded.
        $this->assertCount(2, $names);
        foreach ($names as $n) {
            $this->assertStringEndsWith('.csv', $n);
        }
    }

    public function test_dashboard_export_returns_422_when_no_exportable_widgets(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboard, $_metric] = $this->makeMetricWidget();

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboard->id}/export");
        $response->assertStatus(422);
    }

    public function test_widget_export_mismatched_dashboard_404(): void
    {
        $this->seedBase();
        [$user, $token] = $this->makeSanctumUser();
        [$dashboardA, $widgetA] = $this->makeTableWidget();
        [$dashboardB, $_] = $this->makeTableWidget(withName: 'Other Dashboard');

        $response = $this->withToken($token)
            ->getJson("/api/v1/dashboards/{$dashboardB->id}/widgets/{$widgetA->id}/export");
        $response->assertNotFound();
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeSanctumUser(): array
    {
        $user = User::create([
            'first_name' => 'API',
            'last_name' => 'User',
            'email' => 'api-export@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $token = $user->createToken('test-export')->plainTextToken;

        return [$user, $token];
    }

    private function makeTableWidget(?ReportDashboard $existingDashboard = null, string $withName = 'Test Dashboard'): array
    {
        $dashboard = $existingDashboard ?: ReportDashboard::create([
            'name' => $withName,
            'description' => 't',
            'layout_columns' => 2,
            'visibility' => 'all',
            'is_active' => true,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'Repair Table',
            'widget_type' => 'table',
            'data_source' => 'repair_requests',
            'config' => [
                'columns' => ['reference_no', 'status', 'created_at'],
                'per_page' => 10,
            ],
            'col_span' => 2,
            'sort_order' => 1,
        ]);

        return [$dashboard, $widget];
    }

    private function makeChartWidget(?ReportDashboard $existingDashboard = null): array
    {
        $dashboard = $existingDashboard ?: ReportDashboard::create([
            'name' => 'Chart Dashboard',
            'layout_columns' => 2,
            'visibility' => 'all',
            'is_active' => true,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'Repair by Status',
            'widget_type' => 'chart',
            'data_source' => 'repair_requests',
            'config' => [
                'chart_type' => 'bar',
                'aggregation' => 'count',
                'field' => 'id',
                'group_by' => 'status',
            ],
            'col_span' => 1,
            'sort_order' => 2,
        ]);

        return [$dashboard, $widget];
    }

    private function makeMetricWidget(?ReportDashboard $existingDashboard = null): array
    {
        $dashboard = $existingDashboard ?: ReportDashboard::create([
            'name' => 'Metric Dashboard',
            'layout_columns' => 1,
            'visibility' => 'all',
            'is_active' => true,
        ]);
        $widget = ReportDashboardWidget::create([
            'dashboard_id' => $dashboard->id,
            'title' => 'Total Repairs',
            'widget_type' => 'metric',
            'data_source' => 'repair_requests',
            'config' => ['aggregation' => 'count', 'field' => 'id'],
            'col_span' => 1,
            'sort_order' => 3,
        ]);

        return [$dashboard, $widget];
    }

    private function createRepairRequests(int $count): void
    {
        $workflowId = \DB::table('approval_workflows')->insertGetId([
            'name' => 'Test Workflow',
            'document_type' => 'repair_request',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= $count; $i++) {
            ApprovalInstance::create([
                'workflow_id' => $workflowId,
                'document_type' => 'repair_request',
                'reference_no' => "RR-{$i}",
                'status' => $i % 2 === 0 ? 'approved' : 'pending',
                'current_step_no' => 1,
                'requester_user_id' => 1,
            ]);
        }
    }
}
