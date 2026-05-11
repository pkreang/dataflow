<?php

namespace Database\Seeders;

use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed the system-default home dashboards that replace the legacy hardcoded
 * KPI grid on `/dashboard`. Two dashboards are created so the experience is
 * tailored without exposing manager metrics to everyone:
 *
 *  - "Home (Default)"  → all users; "งานของฉัน" view
 *  - "Home (Manager)"  → only users with `approval.approve`; system-wide KPIs
 *
 * Idempotent — keyed by `name` so reseeds don't duplicate. Sets
 * `settings.default_home_dashboard_id` to the default home so any user without
 * a personal pick lands here automatically.
 */
class HomeDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('is_super_admin', true)->first();
        $createdBy = $admin?->id;

        $defaultDashboard = $this->seedDashboard([
            'name' => 'Home (Default)',
            'description' => 'งานของฉัน — แดชบอร์ดเริ่มต้นสำหรับผู้ใช้ทุกคน',
            'layout_columns' => 3,
            'visibility' => 'all',
            'required_permission' => null,
            'is_active' => true,
            'created_by' => $createdBy,
            'widgets' => [
                [
                    'title' => 'งานรออนุมัติของฉัน',
                    'widget_type' => 'metric',
                    'data_source' => 'school_eforms_pending',
                    'config' => [
                        'aggregation' => 'count',
                        'field' => 'id',
                        'filters' => [
                            'requester_user_id' => '{current_user}',
                        ],
                    ],
                ],
                [
                    'title' => 'แบบร่างของฉัน',
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
                ],
                [
                    'title' => 'ฟอร์มที่ส่งเดือนนี้',
                    'widget_type' => 'metric',
                    'data_source' => 'document_form_submissions',
                    'config' => [
                        'aggregation' => 'count',
                        'field' => 'id',
                        'date_field' => 'created_at',
                        'date_preset' => 'this_month',
                        'filters' => [
                            'user_id' => '{current_user}',
                            'status' => 'submitted',
                        ],
                    ],
                ],
            ],
        ]);

        $this->seedDashboard([
            'name' => 'Home (Manager)',
            'description' => 'ภาพรวมระบบสำหรับผู้อนุมัติและผู้ดูแล',
            'layout_columns' => 3,
            'visibility' => 'permission',
            'required_permission' => 'approval.approve',
            'is_active' => true,
            'created_by' => $createdBy,
            'widgets' => [
                [
                    'title' => 'รออนุมัติทั้งระบบ',
                    'widget_type' => 'metric',
                    'data_source' => 'school_eforms_pending',
                    'config' => [
                        'aggregation' => 'count',
                        'field' => 'id',
                    ],
                ],
                [
                    'title' => 'Submissions เดือนนี้',
                    'widget_type' => 'metric',
                    'data_source' => 'document_form_submissions',
                    'config' => [
                        'aggregation' => 'count',
                        'field' => 'id',
                        'date_field' => 'created_at',
                        'date_preset' => 'this_month',
                        'filters' => [
                            'status' => 'submitted',
                        ],
                    ],
                ],
                [
                    'title' => 'ผู้ใช้งาน',
                    'widget_type' => 'metric',
                    'data_source' => 'users',
                    'config' => [
                        'aggregation' => 'count',
                        'field' => 'id',
                        'filters' => [
                            'is_active' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        // Pin the default as global fallback so the resolver never lands on
        // an arbitrary first-id dashboard the admin might have created later.
        Setting::set('default_home_dashboard_id', (string) $defaultDashboard->id);
    }

    /**
     * Upsert a dashboard + replace its widgets idempotently. Widgets are wiped
     * and re-inserted on each run so config edits in this seeder propagate
     * cleanly without leaving stale rows when widget titles change.
     */
    private function seedDashboard(array $data): ReportDashboard
    {
        $widgets = $data['widgets'];
        unset($data['widgets']);

        $dashboard = ReportDashboard::updateOrCreate(
            ['name' => $data['name']],
            $data
        );

        $dashboard->widgets()->delete();

        foreach ($widgets as $index => $widget) {
            ReportDashboardWidget::create(array_merge($widget, [
                'dashboard_id' => $dashboard->id,
                'sort_order' => $index + 1,
                'col_span' => $widget['col_span'] ?? 0,
            ]));
        }

        return $dashboard;
    }
}
