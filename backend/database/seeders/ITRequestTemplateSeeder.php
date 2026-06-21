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
 * IT Request (ใบขอใช้งาน IT) template.
 *
 * Idempotent — safe to re-run.
 *
 * Usage:
 *   php artisan db:seed --class=ITRequestTemplateSeeder
 */
class ITRequestTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'it_request';

    private const FORM_KEY = 'it_request_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedLookup();
        $this->seedRunningNumber();
        $form = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('ITRequest template ready.');
        $this->command?->info('  Form  : '.self::FORM_KEY.' (id='.$form->id.')');
        $this->command?->info('  Menu  : /forms/'.self::FORM_KEY.'/submissions');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en' => 'IT Request',
                'label_th' => 'ใบขอใช้งาน IT',
                'icon' => 'computer-desktop',
                'sort_order' => 44,
                'routing_mode' => 'hybrid',
                'is_active' => true,
            ]
        );
    }

    private function seedLookup(): void
    {
        $list = LookupList::updateOrCreate(
            ['key' => 'it_request_type'],
            [
                'label_en' => 'IT Request Type',
                'label_th' => 'ประเภทคำขอ IT',
                'is_active' => true,
                'sort_order' => 31,
            ]
        );

        $items = [
            ['value' => 'new_hardware',    'label_en' => 'New Hardware',       'label_th' => 'ขอ Hardware ใหม่',     'sort_order' => 1],
            ['value' => 'new_software',    'label_en' => 'New Software',       'label_th' => 'ขอ Software ใหม่',     'sort_order' => 2],
            ['value' => 'repair',          'label_en' => 'Repair',             'label_th' => 'ซ่อมแซม',              'sort_order' => 3],
            ['value' => 'access_request',  'label_en' => 'Access Request',     'label_th' => 'ขอสิทธิ์เข้าถึง',      'sort_order' => 4],
            ['value' => 'network',         'label_en' => 'Network / Internet', 'label_th' => 'เครือข่าย / Internet', 'sort_order' => 5],
            ['value' => 'other',           'label_en' => 'Other',              'label_th' => 'อื่นๆ',                'sort_order' => 6],
        ];

        foreach ($items as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $list->id, 'value' => $item['value']],
                [
                    'label_en' => $item['label_en'],
                    'label_th' => $item['label_th'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedRunningNumber(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'prefix' => 'IT',
                'digit_count' => 4,
                'reset_mode' => 'yearly',
                'include_year' => true,
                'include_month' => false,
                'is_active' => true,
            ]
        );
    }

    private function seedForm(): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => self::FORM_KEY],
            [
                'name' => 'ใบขอใช้งาน IT',
                'document_type' => self::DOCUMENT_TYPE,
                'description' => 'ฟอร์มยื่นคำขอด้าน IT (Hardware / Software / เครือข่าย)',
                'is_active' => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key' => 'reference_no',
                'label' => 'เลขที่คำขอ',
                'label_en' => 'Reference No.',
                'label_th' => 'เลขที่คำขอ',
                'field_type' => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 1,
                'col_span' => 2,
            ],
            [
                'field_key' => 'request_type',
                'label' => 'ประเภทคำขอ',
                'label_en' => 'Request Type',
                'label_th' => 'ประเภทคำขอ',
                'field_type' => 'lookup',
                'is_required' => true,
                'sort_order' => 2,
                'col_span' => 2,
                'options' => ['source' => 'it_request_type'],
            ],
            [
                'field_key' => 'asset_description',
                'label' => 'ชื่อ/รายละเอียด Asset',
                'label_en' => 'Asset Description',
                'label_th' => 'ชื่อ/รายละเอียด Asset',
                'field_type' => 'text',
                'is_required' => true,
                'sort_order' => 3,
                'col_span' => 2,
            ],
            [
                'field_key' => 'details',
                'label' => 'รายละเอียดเพิ่มเติม',
                'label_en' => 'Details',
                'label_th' => 'รายละเอียดเพิ่มเติม',
                'field_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 4,
                'col_span' => 2,
            ],
            [
                'field_key' => 'urgency',
                'label' => 'ความเร่งด่วน',
                'label_en' => 'Urgency',
                'label_th' => 'ความเร่งด่วน',
                'field_type' => 'select',
                'is_required' => true,
                'sort_order' => 5,
                'col_span' => 1,
                'options' => ['ต่ำ (Low)', 'ปานกลาง (Medium)', 'สูง (High)'],
            ],
            [
                'field_key' => 'required_date',
                'label' => 'ต้องการภายในวันที่',
                'label_en' => 'Required By',
                'label_th' => 'ต้องการภายในวันที่',
                'field_type' => 'date',
                'is_required' => false,
                'sort_order' => 6,
                'col_span' => 1,
            ],
            [
                'field_key' => 'attachment',
                'label' => 'เอกสารแนบ',
                'label_en' => 'Attachment',
                'label_th' => 'เอกสารแนบ',
                'field_type' => 'file',
                'is_required' => false,
                'sort_order' => 7,
                'col_span' => 2,
            ],
            [
                'field_key' => 'signature',
                'label' => 'ลายเซ็นผู้ขอ',
                'label_en' => 'Signature',
                'label_th' => 'ลายเซ็นผู้ขอ',
                'field_type' => 'signature',
                'is_required' => true,
                'sort_order' => 8,
                'col_span' => 2,
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
            ['name' => 'อนุมัติคำขอ IT (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description' => 'workflow อนุมัติคำขอ IT — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active' => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'ผู้อนุมัติคำขอ IT',
            'approver_type' => 'role',
            'approver_ref' => 'approver',
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        return $workflow;
    }

    private function seedPolicy(DocumentForm $form, ApprovalWorkflow $workflow): void
    {
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            [
                'workflow_id' => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
