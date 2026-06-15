<?php

namespace Database\Seeders;

use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('is_super_admin', true)->value('id');

        $this->seedDashboard([
            'name'                => 'ภาพรวมเอกสาร',
            'description'         => 'สถิติเอกสารและใบลาทั้งระบบ',
            'layout_columns'      => 3,
            'visibility'          => 'all',
            'required_permission' => null,
            'is_active'           => true,
            'created_by'          => $adminId,
            'widgets' => [
                [
                    'title'       => 'เอกสารทั้งหมด',
                    'widget_type' => 'metric',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id'],
                ],
                [
                    'title'       => 'รออนุมัติ',
                    'widget_type' => 'metric',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id', 'filters' => ['status' => 'submitted']],
                ],
                [
                    'title'       => 'แบบร่าง',
                    'widget_type' => 'metric',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id', 'filters' => ['status' => 'draft']],
                ],
                [
                    'title'       => 'สถานะเอกสาร',
                    'widget_type' => 'chart',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 1,
                    'config'      => ['chart_type' => 'donut', 'aggregation' => 'count', 'group_by' => 'status'],
                ],
                [
                    'title'       => 'ตามฟอร์ม',
                    'widget_type' => 'chart',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 2,
                    'config'      => ['chart_type' => 'bar', 'aggregation' => 'count', 'group_by' => 'form_id'],
                ],
                [
                    'title'       => 'รายการล่าสุด',
                    'widget_type' => 'table',
                    'data_source' => 'document_form_submissions',
                    'col_span'    => 3,
                    'config'      => [
                        'columns'  => ['reference_no', 'status', 'form_id', 'created_at'],
                        'per_page' => 10,
                    ],
                ],
            ],
        ]);

        $this->seedDashboard([
            'name'                => 'ภาพรวมการแจ้งซ่อม',
            'description'         => 'สถิติการแจ้งซ่อมและบำรุงรักษา',
            'layout_columns'      => 3,
            'visibility'          => 'all',
            'required_permission' => null,
            'is_active'           => true,
            'created_by'          => $adminId,
            'widgets' => [
                [
                    'title'       => 'แจ้งซ่อมทั้งหมด',
                    'widget_type' => 'metric',
                    'data_source' => 'repair_requests',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id'],
                ],
                [
                    'title'       => 'รอดำเนินการ',
                    'widget_type' => 'metric',
                    'data_source' => 'repair_requests',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id', 'filters' => ['status' => 'pending']],
                ],
                [
                    'title'       => 'อนุมัติแล้ว',
                    'widget_type' => 'metric',
                    'data_source' => 'repair_requests',
                    'col_span'    => 1,
                    'config'      => ['aggregation' => 'count', 'field' => 'id', 'filters' => ['status' => 'approved']],
                ],
                [
                    'title'       => 'สถานะการแจ้งซ่อม',
                    'widget_type' => 'chart',
                    'data_source' => 'repair_requests',
                    'col_span'    => 1,
                    'config'      => ['chart_type' => 'donut', 'aggregation' => 'count', 'group_by' => 'status'],
                ],
                [
                    'title'       => 'ตามแผนก',
                    'widget_type' => 'chart',
                    'data_source' => 'repair_requests',
                    'col_span'    => 2,
                    'config'      => ['chart_type' => 'bar', 'aggregation' => 'count', 'group_by' => 'department_id'],
                ],
                [
                    'title'       => 'รายการล่าสุด',
                    'widget_type' => 'table',
                    'data_source' => 'repair_requests',
                    'col_span'    => 3,
                    'config'      => [
                        'columns'  => ['reference_no', 'status', 'department_id', 'created_at'],
                        'per_page' => 10,
                    ],
                ],
            ],
        ]);
    }

    private function seedDashboard(array $data): void
    {
        $widgets = $data['widgets'];
        unset($data['widgets']);

        $dashboard = ReportDashboard::updateOrCreate(['name' => $data['name']], $data);
        $dashboard->widgets()->delete();

        foreach ($widgets as $index => $widget) {
            ReportDashboardWidget::create(array_merge($widget, [
                'dashboard_id' => $dashboard->id,
                'sort_order'   => $index + 1,
            ]));
        }
    }
}
