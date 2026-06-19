<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\RunningNumberConfig;
use Illuminate\Database\Seeder;

/**
 * Memo / Circular (Memo / หนังสือเวียน) template.
 *
 * Idempotent — safe to re-run.
 *
 * Usage:
 *   php artisan db:seed --class=MemoTemplateSeeder
 */
class MemoTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'memo';
    private const FORM_KEY      = 'memo_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedRunningNumber();
        $form     = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('Memo template ready.');
        $this->command?->info('  Form  : '.self::FORM_KEY.' (id='.$form->id.')');
        $this->command?->info('  Menu  : /forms/'.self::FORM_KEY.'/submissions');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en'     => 'Memo / Circular',
                'label_th'     => 'Memo / หนังสือเวียน',
                'icon'         => 'document-text',
                'sort_order'   => 43,
                'routing_mode' => 'organization_wide',
                'is_active'    => true,
            ]
        );
    }

    private function seedRunningNumber(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'prefix'        => 'MEM',
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
                'name'           => 'Memo / หนังสือเวียน',
                'document_type'  => self::DOCUMENT_TYPE,
                'description'    => 'ฟอร์มเขียน Memo หรือหนังสือเวียนภายใน',
                'is_active'      => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key'   => 'reference_no',
                'label'       => 'เลขที่หนังสือ',
                'label_en'    => 'Reference No.',
                'label_th'    => 'เลขที่หนังสือ',
                'field_type'  => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order'  => 1,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'to_field',
                'label'       => 'ถึง',
                'label_en'    => 'To',
                'label_th'    => 'ถึง',
                'field_type'  => 'text',
                'is_required' => true,
                'sort_order'  => 2,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'cc_field',
                'label'       => 'สำเนา',
                'label_en'    => 'CC',
                'label_th'    => 'สำเนา',
                'field_type'  => 'text',
                'is_required' => false,
                'sort_order'  => 3,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'subject',
                'label'       => 'เรื่อง',
                'label_en'    => 'Subject',
                'label_th'    => 'เรื่อง',
                'field_type'  => 'text',
                'is_required' => true,
                'sort_order'  => 4,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'memo_date',
                'label'       => 'วันที่',
                'label_en'    => 'Date',
                'label_th'    => 'วันที่',
                'field_type'  => 'date',
                'is_required' => true,
                'sort_order'  => 5,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'body',
                'label'       => 'เนื้อหา',
                'label_en'    => 'Body',
                'label_th'    => 'เนื้อหา',
                'field_type'  => 'textarea',
                'is_required' => true,
                'sort_order'  => 6,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'attachment',
                'label'       => 'เอกสารแนบ',
                'label_en'    => 'Attachment',
                'label_th'    => 'เอกสารแนบ',
                'field_type'  => 'file',
                'is_required' => false,
                'sort_order'  => 7,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'signature',
                'label'       => 'ลายเซ็นผู้ออก',
                'label_en'    => 'Signature',
                'label_th'    => 'ลายเซ็นผู้ออก',
                'field_type'  => 'signature',
                'is_required' => true,
                'sort_order'  => 8,
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
            ['name' => 'อนุมัติ Memo (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description'   => 'workflow อนุมัติ Memo / หนังสือเวียน — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active'     => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id'   => $workflow->id,
            'step_no'       => 1,
            'name'          => 'ผู้อนุมัติ Memo',
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
            ['form_id' => $form->id],
            [
                'workflow_id'          => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
