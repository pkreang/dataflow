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
 * Expense Claim (ใบเบิกค่าใช้จ่าย) template.
 *
 * Idempotent — safe to re-run.
 *
 * Usage:
 *   php artisan db:seed --class=ExpenseClaimTemplateSeeder
 */
class ExpenseClaimTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'expense_claim';
    private const FORM_KEY      = 'expense_claim_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedLookup();
        $this->seedRunningNumber();
        $form     = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('ExpenseClaim template ready.');
        $this->command?->info('  Form  : '.self::FORM_KEY.' (id='.$form->id.')');
        $this->command?->info('  Menu  : /forms/'.self::FORM_KEY.'/submissions');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en'     => 'Expense Claim',
                'label_th'     => 'ใบเบิกค่าใช้จ่าย',
                'icon'         => 'currency-dollar',
                'sort_order'   => 42,
                'routing_mode' => 'hybrid',
                'is_active'    => true,
            ]
        );
    }

    private function seedLookup(): void
    {
        $list = LookupList::updateOrCreate(
            ['key' => 'expense_category'],
            [
                'label_en'   => 'Expense Category',
                'label_th'   => 'หมวดหมู่ค่าใช้จ่าย',
                'is_active'  => true,
                'sort_order' => 30,
            ]
        );

        $items = [
            ['value' => 'transport',      'label_en' => 'Transportation',   'label_th' => 'ค่าเดินทาง',       'sort_order' => 1],
            ['value' => 'meals',          'label_en' => 'Meals',            'label_th' => 'ค่าอาหาร',          'sort_order' => 2],
            ['value' => 'accommodation',  'label_en' => 'Accommodation',    'label_th' => 'ค่าที่พัก',          'sort_order' => 3],
            ['value' => 'training',       'label_en' => 'Training',         'label_th' => 'ค่าอบรม/สัมมนา',    'sort_order' => 4],
            ['value' => 'supplies',       'label_en' => 'Office Supplies',  'label_th' => 'ค่าวัสดุสำนักงาน',  'sort_order' => 5],
            ['value' => 'other',          'label_en' => 'Other',            'label_th' => 'อื่นๆ',              'sort_order' => 6],
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
                'prefix'        => 'EX',
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
                'name'           => 'ใบเบิกค่าใช้จ่าย',
                'document_type'  => self::DOCUMENT_TYPE,
                'description'    => 'ฟอร์มยื่นขอเบิกค่าใช้จ่าย',
                'is_active'      => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key'   => 'reference_no',
                'label'       => 'เลขที่เอกสาร',
                'label_en'    => 'Reference No.',
                'label_th'    => 'เลขที่เอกสาร',
                'field_type'  => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order'  => 1,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'expense_date',
                'label'       => 'วันที่เกิดค่าใช้จ่าย',
                'label_en'    => 'Expense Date',
                'label_th'    => 'วันที่เกิดค่าใช้จ่าย',
                'field_type'  => 'date',
                'is_required' => true,
                'sort_order'  => 2,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'expense_category',
                'label'       => 'หมวดหมู่',
                'label_en'    => 'Category',
                'label_th'    => 'หมวดหมู่',
                'field_type'  => 'lookup',
                'is_required' => true,
                'sort_order'  => 3,
                'col_span'    => 1,
                'options'     => ['source' => 'expense_category'],
            ],
            [
                'field_key'   => 'description',
                'label'       => 'รายละเอียด',
                'label_en'    => 'Description',
                'label_th'    => 'รายละเอียด',
                'field_type'  => 'textarea',
                'is_required' => true,
                'sort_order'  => 4,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'amount',
                'label'       => 'จำนวนเงิน (บาท)',
                'label_en'    => 'Amount (THB)',
                'label_th'    => 'จำนวนเงิน (บาท)',
                'field_type'  => 'currency',
                'is_required' => true,
                'sort_order'  => 5,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'receipt',
                'label'       => 'แนบใบเสร็จ/หลักฐาน',
                'label_en'    => 'Receipt / Evidence',
                'label_th'    => 'แนบใบเสร็จ/หลักฐาน',
                'field_type'  => 'file',
                'is_required' => true,
                'sort_order'  => 6,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'signature',
                'label'       => 'ลายเซ็นผู้ขอเบิก',
                'label_en'    => 'Signature',
                'label_th'    => 'ลายเซ็นผู้ขอเบิก',
                'field_type'  => 'signature',
                'is_required' => true,
                'sort_order'  => 7,
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
            ['name' => 'อนุมัติใบเบิกค่าใช้จ่าย (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description'   => 'workflow อนุมัติใบเบิกค่าใช้จ่าย — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active'     => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id'   => $workflow->id,
            'step_no'       => 1,
            'name'          => 'ผู้อนุมัติค่าใช้จ่าย',
            'approver_type' => 'role',
            'approver_ref'  => 'approver',
            'min_approvals' => 1,
            'is_active'     => true,
        ]);

        return $workflow;
    }

    private function seedPolicy(DocumentForm $form, ApprovalWorkflow $workflow): void
    {
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id, 'department_id' => null],
            [
                'workflow_id'          => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
