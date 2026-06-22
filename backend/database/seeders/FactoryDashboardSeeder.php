<?php

namespace Database\Seeders;

use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Factory (CMMS-flavored) demo dashboards. Seeds two:
 *   - "CMMS — Repair Requests Overview" (full overview report; opt-in via /reports)
 *   - "Home (Factory)" (per-user landing page, set as default home so NTEQ users
 *     never land on the school-flavoured Home (Default) that ships from the
 *     base HomeDashboardSeeder)
 *
 * Both data sources point at repair_requests so a fresh
 * `composer switch:factory` lands the user on a populated home — not the
 * school KPI grid that returns zero for everything in factory deployments.
 */
class FactoryDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $adminId = $admin?->id ?? 1;

        $dashboard = ReportDashboard::updateOrCreate(
            ['name' => 'CMMS — Repair Requests Overview'],
            [
                'description' => 'ภาพรวมใบแจ้งซ่อม',
                'layout_columns' => 2,
                'visibility' => 'all',
                'required_permission' => null,
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );

        // Rebuild widgets each run so schema tweaks propagate on `switch:factory`.
        $dashboard->widgets()->delete();

        $widgets = [
            [
                'title' => 'ใบแจ้งซ่อมทั้งหมด',
                'widget_type' => 'metric',
                'data_source' => 'repair_requests',
                'config' => ['aggregation' => 'count', 'field' => 'id'],
                'col_span' => 1,
                'sort_order' => 1,
            ],
            [
                'title' => 'ตามสถานะ',
                'widget_type' => 'chart',
                'data_source' => 'repair_requests',
                'config' => ['chart_type' => 'donut', 'group_by' => 'status', 'aggregation' => 'count'],
                'col_span' => 1,
                'sort_order' => 2,
            ],
            [
                'title' => 'ตามแผนกผู้ขอ',
                'widget_type' => 'chart',
                'data_source' => 'repair_requests',
                'config' => ['chart_type' => 'bar', 'group_by' => 'org_unit_id', 'aggregation' => 'count'],
                'col_span' => 2,
                'sort_order' => 3,
            ],
            [
                'title' => 'รายการล่าสุด',
                'widget_type' => 'table',
                'data_source' => 'repair_requests',
                'config' => [
                    'columns' => ['reference_no', 'status', 'org_unit_id', 'created_at'],
                    'per_page' => 10,
                ],
                'col_span' => 2,
                'sort_order' => 4,
            ],
        ];

        foreach ($widgets as $w) {
            ReportDashboardWidget::create(array_merge($w, ['dashboard_id' => $dashboard->id]));
        }

        // ── Home (Factory) ─────────────────────────────────────
        // Per-user home page tailored for CMMS users. Replaces the
        // base HomeDashboardSeeder's school-flavoured "Home (Default)"
        // as the system-wide default for factory deployments.
        $homeFactory = ReportDashboard::updateOrCreate(
            ['name' => 'Home (Factory)'],
            [
                'description' => 'งานของฉัน — แดชบอร์ดเริ่มต้นสำหรับโรงงาน',
                'layout_columns' => 3,
                'visibility' => 'all',
                'required_permission' => null,
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );
        $homeFactory->widgets()->delete();

        $homeWidgets = [
            [
                'title' => 'ใบแจ้งซ่อมที่ฉันยื่น',
                'widget_type' => 'metric',
                'data_source' => 'repair_requests',
                'config' => [
                    'aggregation' => 'count',
                    'field' => 'id',
                    'filters' => ['requester_user_id' => '{current_user}'],
                ],
                'col_span' => 1,
                'sort_order' => 1,
            ],
            [
                'title' => 'รออนุมัติ (ใบแจ้งซ่อม)',
                'widget_type' => 'metric',
                'data_source' => 'repair_requests',
                'config' => [
                    'aggregation' => 'count',
                    'field' => 'id',
                    'filters' => ['status' => 'pending'],
                ],
                'col_span' => 1,
                'sort_order' => 2,
            ],
        ];

        foreach ($homeWidgets as $w) {
            ReportDashboardWidget::create(array_merge($w, ['dashboard_id' => $homeFactory->id]));
        }

        // Override default home: HomeDashboardSeeder (if it ran via the school
        // flow on the same install) sets this to "Home (Default)". For factory
        // installs that pointer should land on the factory home instead.
        Setting::set('default_home_dashboard_id', (string) $homeFactory->id);
    }
}
