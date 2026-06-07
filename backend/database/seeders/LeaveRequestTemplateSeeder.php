<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\RunningNumberConfig;
use Illuminate\Database\Seeder;

/**
 * Generic Leave Request (ใบลา) template.
 *
 * document_type : leave_request
 * form_key      : leave_request_default
 *
 * Idempotent — safe to re-run.
 * Navigation menu auto-created by DocumentForm::saved observer.
 *
 * Usage:
 *   php artisan db:seed --class=LeaveRequestTemplateSeeder
 */
class LeaveRequestTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'leave_request';
    private const FORM_KEY      = 'leave_request_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedLookup();
        $this->seedRunningNumber();
        $form     = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('Leave Request template ready.');
        $this->command?->info('  Form   : '.$form->form_key.' (id='.$form->id.')');
        $this->command?->info('  Menu   : /forms/'.self::FORM_KEY.'/submissions');
        $this->command?->info('  Running: LV-{YEAR}-0001');
        $this->command?->info('  Note   : Workflow stage uses approver_type=role, approver_ref=approver');
        $this->command?->info('           Reassign via Settings > Workflows if needed.');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en'     => 'Leave Request',
                'label_th'     => 'ใบลา',
                'icon'         => 'calendar-days',
                'sort_order'   => 30,
                'routing_mode' => 'organization_wide',
                'is_active'    => true,
            ]
        );
    }

    private function seedLookup(): void
    {
        $list = LookupList::updateOrCreate(
            ['key' => 'leave_type'],
            [
                'label_en'   => 'Leave Type',
                'label_th'   => 'ประเภทการลา',
                'is_active'  => true,
                'sort_order' => 20,
            ]
        );

        $items = [
            ['value' => 'sick',       'label_en' => 'Sick Leave',       'label_th' => 'ลาป่วย',    'sort_order' => 1],
            ['value' => 'personal',   'label_en' => 'Personal Leave',   'label_th' => 'ลากิจ',     'sort_order' => 2],
            ['value' => 'vacation',   'label_en' => 'Vacation Leave',   'label_th' => 'ลาพักผ่อน', 'sort_order' => 3],
            ['value' => 'maternity',  'label_en' => 'Maternity Leave',  'label_th' => 'ลาคลอด',    'sort_order' => 4],
            ['value' => 'ordination', 'label_en' => 'Ordination Leave', 'label_th' => 'ลาอุปสมบท', 'sort_order' => 5],
            ['value' => 'other',      'label_en' => 'Other',            'label_th' => 'อื่นๆ',     'sort_order' => 6],
        ];

        foreach ($items as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $list->id, 'value' => $item['value']],
                [
                    'label_en'   => $item['label_en'],
                    'label_th'   => $item['label_th'],
                    'sort_order' => $item['sort_order'],
                    'is_active'  => true,
                ]
            );
        }
    }

    private function seedRunningNumber(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'prefix'        => 'LV',
                'digit_count'   => 4,
                'reset_mode'    => 'yearly',
                'include_year'  => true,
                'include_month' => false,
                'is_active'     => true,
            ]
        );
    }

    private function seedForm(): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => self::FORM_KEY],
            [
                'name'           => 'ใบลา',
                'document_type'  => self::DOCUMENT_TYPE,
                'description'    => 'ฟอร์มยื่นคำขอลาหยุด',
                'is_active'      => true,
                'layout_columns' => 2,
            ]
        );

        // Delete+recreate for idempotency (avoids duplicate sort_order rows)
        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key'        => 'reference_no',
                'label'            => 'เลขที่ใบลา',
                'label_en'         => 'Reference No.',
                'label_th'         => 'เลขที่ใบลา',
                'field_type'       => 'auto_number',
                'is_required'      => false,
                'is_readonly'      => true,
                'sort_order'       => 1,
                'col_span'         => 2,
            ],
            [
                'field_key'       => 'leave_type',
                'label'           => 'ประเภทการลา',
                'label_en'        => 'Leave Type',
                'label_th'        => 'ประเภทการลา',
                'field_type'      => 'lookup',
                'is_required'     => true,
                'is_searchable'   => true,
                'sort_order'      => 2,
                'col_span'        => 2,
                'options'         => ['source' => 'leave_type'],
            ],
            [
                'field_key'     => 'date_from',
                'label'         => 'วันที่เริ่มลา',
                'label_en'      => 'From',
                'label_th'      => 'วันที่เริ่มลา',
                'field_type'    => 'date',
                'is_required'   => true,
                'is_searchable' => true,
                'sort_order'    => 3,
                'col_span'      => 1,
            ],
            [
                'field_key'     => 'date_to',
                'label'         => 'วันที่สิ้นสุด',
                'label_en'      => 'To',
                'label_th'      => 'วันที่สิ้นสุด',
                'field_type'    => 'date',
                'is_required'   => true,
                'is_searchable' => true,
                'sort_order'    => 4,
                'col_span'      => 1,
            ],
            [
                'field_key'  => 'total_days',
                'label'      => 'จำนวนวัน',
                'label_en'   => 'Total Days',
                'label_th'   => 'จำนวนวัน',
                'field_type' => 'formula',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 5,
                'col_span'   => 2,
                'options'    => ['expression' => 'DAYS(date_from, date_to)', 'decimals' => 0],
            ],
            [
                'field_key'        => 'sick_certificate',
                'label'            => 'ใบรับรองแพทย์',
                'label_en'         => 'Medical Certificate',
                'label_th'         => 'ใบรับรองแพทย์',
                'field_type'       => 'file',
                'is_required'      => false,
                'sort_order'       => 6,
                'col_span'         => 2,
                'visibility_rules' => [
                    ['field' => 'leave_type', 'operator' => 'equals', 'value' => 'sick'],
                ],
                'required_rules'   => [
                    ['conditions' => [
                        ['field' => 'leave_type', 'operator' => 'equals', 'value' => 'sick'],
                    ]],
                ],
            ],
            [
                'field_key'   => 'substitute',
                'label'       => 'ผู้ปฏิบัติหน้าที่แทน',
                'label_en'    => 'Substitute',
                'label_th'    => 'ผู้ปฏิบัติหน้าที่แทน',
                'field_type'  => 'text',
                'is_required' => false,
                'sort_order'  => 7,
                'col_span'    => 2,
            ],
            [
                'field_key'        => 'reason',
                'label'            => 'เหตุผลในการลา',
                'label_en'         => 'Reason',
                'label_th'         => 'เหตุผลในการลา',
                'field_type'       => 'textarea',
                'is_required'      => false,
                'sort_order'       => 8,
                'col_span'         => 2,
                'validation_rules' => ['min_length' => 5],
            ],
            [
                'field_key'   => 'signature',
                'label'       => 'ลายเซ็นผู้ขอลา',
                'label_en'    => 'Signature',
                'label_th'    => 'ลายเซ็นผู้ขอลา',
                'field_type'  => 'signature',
                'is_required' => true,
                'sort_order'  => 9,
                'col_span'    => 2,
            ],
        ];

        foreach ($fields as $data) {
            DocumentFormField::create(array_merge(['form_id' => $form->id], $data));
        }

        return $form;
    }

    private function seedWorkflow(): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลา (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description'   => 'workflow อนุมัติใบลา — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active'     => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id'   => $workflow->id,
            'step_no'       => 1,
            'name'          => 'ผู้อนุมัติใบลา',
            'approver_type' => 'role',
            'approver_ref'  => 'approver',
            'min_approvals' => 1,
            'is_active'     => true,
        ]);

        return $workflow;
    }

    private function seedPolicy(DocumentForm $form, ApprovalWorkflow $workflow): void
    {
        // department_id = null → applies to all departments (global policy)
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id, 'department_id' => null],
            [
                'workflow_id'          => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
