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
 * Quotation (ใบเสนอราคา) template.
 *
 * Idempotent — safe to re-run.
 *
 * Usage:
 *   php artisan db:seed --class=QuotationTemplateSeeder
 */
class QuotationTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'quotation';
    private const FORM_KEY      = 'quotation_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedRunningNumber();
        $form     = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('Quotation template ready.');
        $this->command?->info('  Form  : '.self::FORM_KEY.' (id='.$form->id.')');
        $this->command?->info('  Menu  : /forms/'.self::FORM_KEY.'/submissions');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en'     => 'Quotation',
                'label_th'     => 'ใบเสนอราคา',
                'icon'         => 'document-text',
                'sort_order'   => 50,
                'routing_mode' => 'hybrid',
                'is_active'    => true,
            ]
        );
    }

    private function seedRunningNumber(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'prefix'        => 'QT',
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
                'name'           => 'ใบเสนอราคา',
                'document_type'  => self::DOCUMENT_TYPE,
                'description'    => 'ฟอร์มใบเสนอราคาสินค้า/บริการ',
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
                'field_key'   => 'quote_date',
                'label'       => 'วันที่ออกใบเสนอราคา',
                'label_en'    => 'Quote Date',
                'label_th'    => 'วันที่ออกใบเสนอราคา',
                'field_type'  => 'date',
                'is_required' => true,
                'sort_order'  => 2,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'validity_date',
                'label'       => 'ใบเสนอราคามีผลถึง',
                'label_en'    => 'Valid Until',
                'label_th'    => 'ใบเสนอราคามีผลถึง',
                'field_type'  => 'date',
                'is_required' => true,
                'sort_order'  => 3,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'customer_section',
                'label'       => 'ข้อมูลลูกค้า / ผู้รับใบเสนอราคา',
                'label_en'    => 'Customer Information',
                'label_th'    => 'ข้อมูลลูกค้า / ผู้รับใบเสนอราคา',
                'field_type'  => 'section',
                'is_required' => false,
                'sort_order'  => 4,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'customer_name',
                'label'       => 'ชื่อบริษัท/ลูกค้า',
                'label_en'    => 'Customer Name',
                'label_th'    => 'ชื่อบริษัท/ลูกค้า',
                'field_type'  => 'text',
                'is_required' => true,
                'sort_order'  => 5,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'customer_contact',
                'label'       => 'ผู้ติดต่อ / เบอร์โทร',
                'label_en'    => 'Contact',
                'label_th'    => 'ผู้ติดต่อ / เบอร์โทร',
                'field_type'  => 'text',
                'is_required' => false,
                'sort_order'  => 6,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'customer_address',
                'label'       => 'ที่อยู่',
                'label_en'    => 'Address',
                'label_th'    => 'ที่อยู่',
                'field_type'  => 'textarea',
                'is_required' => false,
                'sort_order'  => 7,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'items_section',
                'label'       => 'รายการสินค้า / บริการ',
                'label_en'    => 'Items / Services',
                'label_th'    => 'รายการสินค้า / บริการ',
                'field_type'  => 'section',
                'is_required' => false,
                'sort_order'  => 8,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'items',
                'label'       => 'รายการ',
                'label_en'    => 'Items',
                'label_th'    => 'รายการ',
                'field_type'  => 'group',
                'is_required' => false,
                'sort_order'  => 9,
                'col_span'    => 2,
                'options'     => [
                    'fields' => [
                        ['key' => 'description', 'label' => 'รายการ/บริการ', 'label_en' => 'Description', 'label_th' => 'รายการ/บริการ', 'type' => 'text',   'required' => true],
                        ['key' => 'quantity',    'label' => 'จำนวน',         'label_en' => 'Qty',         'label_th' => 'จำนวน',         'type' => 'number', 'required' => true],
                        ['key' => 'unit',        'label' => 'หน่วย',          'label_en' => 'Unit',        'label_th' => 'หน่วย',          'type' => 'text',   'required' => false],
                        ['key' => 'unit_price',  'label' => 'ราคา/หน่วย',    'label_en' => 'Unit Price',  'label_th' => 'ราคา/หน่วย',    'type' => 'number', 'required' => true],
                    ],
                    'min_rows'       => 1,
                    'max_rows'       => 20,
                    'label_singular' => 'รายการ',
                    'layout_columns' => 1,
                ],
            ],
            [
                'field_key'   => 'subtotal_amount',
                'label'       => 'ราคารวมก่อนหักส่วนลด (บาท)',
                'label_en'    => 'Subtotal (THB)',
                'label_th'    => 'ราคารวมก่อนหักส่วนลด (บาท)',
                'field_type'  => 'currency',
                'is_required' => true,
                'sort_order'  => 10,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'discount_amount',
                'label'       => 'ส่วนลด (บาท)',
                'label_en'    => 'Discount (THB)',
                'label_th'    => 'ส่วนลด (บาท)',
                'field_type'  => 'number',
                'is_required' => false,
                'sort_order'  => 11,
                'col_span'    => 1,
            ],
            [
                'field_key'   => 'grand_total',
                'label'       => 'ราคาสุทธิ (บาท)',
                'label_en'    => 'Grand Total (THB)',
                'label_th'    => 'ราคาสุทธิ (บาท)',
                'field_type'  => 'formula',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order'  => 12,
                'col_span'    => 2,
                'options'     => ['expression' => 'subtotal_amount - discount_amount', 'decimals' => 2],
            ],
            [
                'field_key'   => 'terms_section',
                'label'       => 'เงื่อนไขและหมายเหตุ',
                'label_en'    => 'Terms & Notes',
                'label_th'    => 'เงื่อนไขและหมายเหตุ',
                'field_type'  => 'section',
                'is_required' => false,
                'sort_order'  => 13,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'payment_terms',
                'label'       => 'เงื่อนไขการชำระเงิน',
                'label_en'    => 'Payment Terms',
                'label_th'    => 'เงื่อนไขการชำระเงิน',
                'field_type'  => 'textarea',
                'is_required' => false,
                'sort_order'  => 14,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'notes',
                'label'       => 'หมายเหตุ',
                'label_en'    => 'Notes',
                'label_th'    => 'หมายเหตุ',
                'field_type'  => 'textarea',
                'is_required' => false,
                'sort_order'  => 15,
                'col_span'    => 2,
            ],
            [
                'field_key'   => 'signature',
                'label'       => 'ลายเซ็นผู้เสนอราคา',
                'label_en'    => 'Signature',
                'label_th'    => 'ลายเซ็นผู้เสนอราคา',
                'field_type'  => 'signature',
                'is_required' => true,
                'sort_order'  => 16,
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
            ['name' => 'อนุมัติใบเสนอราคา (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description'   => 'workflow อนุมัติใบเสนอราคา — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active'     => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id'   => $workflow->id,
            'step_no'       => 1,
            'name'          => 'ผู้อนุมัติใบเสนอราคา',
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
            ['form_id' => $form->id, 'department_id' => null, 'position_id' => null],
            [
                'workflow_id'          => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
