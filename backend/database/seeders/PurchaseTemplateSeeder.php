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
 * Generic Purchase Request (ใบขอซื้อ) + Purchase Order (ใบสั่งซื้อ) templates.
 *
 * Creates DocumentTypes, DocumentForms (header fields only — line items are
 * managed by PurchaseRequestItem / PurchaseOrderItem models), RunningNumbers,
 * and simple role-based approval workflows.
 *
 * Factory deployments can run PurchaseWorkflowSeeder afterwards to REPLACE the
 * workflows with position/amount-based ones (DEPT_MGR / PLANT_MGR).
 *
 * Idempotent — safe to re-run.
 * Navigation menus auto-created by DocumentForm::saved observer.
 *
 * Usage:
 *   php artisan db:seed --class=PurchaseTemplateSeeder
 */
class PurchaseTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDocumentTypes();
        $this->seedRunningNumbers();

        $prForm = $this->seedPRForm();
        $poForm = $this->seedPOForm();

        $prWorkflow = $this->seedWorkflow(
            'อนุมัติใบขอซื้อ (ค่าเริ่มต้น)',
            'purchase_request',
            'workflow อนุมัติใบขอซื้อ — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
            'ผู้อนุมัติใบขอซื้อ'
        );
        $poWorkflow = $this->seedWorkflow(
            'อนุมัติใบสั่งซื้อ (ค่าเริ่มต้น)',
            'purchase_order',
            'workflow อนุมัติใบสั่งซื้อ — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
            'ผู้อนุมัติใบสั่งซื้อ'
        );

        $this->seedPolicy($prForm, $prWorkflow);
        $this->seedPolicy($poForm, $poWorkflow);

        $this->command?->info('Purchase templates ready.');
        $this->command?->info('  PR form : purchase_request_default (id='.$prForm->id.')');
        $this->command?->info('  PO form : purchase_order_default   (id='.$poForm->id.')');
        $this->command?->info('  Running : PR-{YEAR}-0001 / PO-{YEAR}-0001');
        $this->command?->info('  Note    : Factory mode — run PurchaseWorkflowSeeder to add amount-based routing.');
    }

    private function seedDocumentTypes(): void
    {
        DocumentType::firstOrCreate(
            ['code' => 'purchase_request'],
            [
                'label_en' => 'Purchase Request',
                'label_th' => 'ใบขอซื้อ',
                'icon' => 'shopping-cart',
                'sort_order' => 40,
                'routing_mode' => 'hybrid',
                'is_active' => true,
            ]
        );

        DocumentType::firstOrCreate(
            ['code' => 'purchase_order'],
            [
                'label_en' => 'Purchase Order',
                'label_th' => 'ใบสั่งซื้อ',
                'icon' => 'document-check',
                'sort_order' => 41,
                'routing_mode' => 'organization_wide',
                'is_active' => true,
            ]
        );
    }

    private function seedRunningNumbers(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'purchase_request'],
            [
                'prefix' => 'PR',
                'digit_count' => 4,
                'reset_mode' => 'yearly',
                'include_year' => true,
                'include_month' => false,
                'is_active' => true,
            ]
        );

        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'purchase_order'],
            [
                'prefix' => 'PO',
                'digit_count' => 4,
                'reset_mode' => 'yearly',
                'include_year' => true,
                'include_month' => false,
                'is_active' => true,
            ]
        );
    }

    private function seedPRForm(): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => 'purchase_request_default'],
            [
                'name' => 'ใบขอซื้อ',
                'document_type' => 'purchase_request',
                'description' => 'ฟอร์มยื่นคำขออนุมัติซื้อสินค้า/บริการ',
                'is_active' => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key' => 'reference_no',
                'label' => 'เลขที่ใบขอซื้อ',
                'label_en' => 'Reference No.',
                'label_th' => 'เลขที่ใบขอซื้อ',
                'field_type' => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 1,
                'col_span' => 2,
            ],
            [
                'field_key' => 'request_date',
                'label' => 'วันที่ขอซื้อ',
                'label_en' => 'Request Date',
                'label_th' => 'วันที่ขอซื้อ',
                'field_type' => 'date',
                'is_required' => true,
                'sort_order' => 2,
                'col_span' => 1,
            ],
            [
                'field_key' => 'required_date',
                'label' => 'ต้องการภายในวันที่',
                'label_en' => 'Required By',
                'label_th' => 'ต้องการภายในวันที่',
                'field_type' => 'date',
                'is_required' => false,
                'sort_order' => 3,
                'col_span' => 1,
            ],
            [
                'field_key' => 'purpose',
                'label' => 'วัตถุประสงค์',
                'label_en' => 'Purpose',
                'label_th' => 'วัตถุประสงค์',
                'field_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 4,
                'col_span' => 2,
            ],
            [
                'field_key' => 'notes',
                'label' => 'หมายเหตุ',
                'label_en' => 'Notes',
                'label_th' => 'หมายเหตุ',
                'field_type' => 'textarea',
                'is_required' => false,
                'sort_order' => 5,
                'col_span' => 2,
            ],
            [
                'field_key' => 'signature',
                'label' => 'ลายเซ็นผู้ขอ',
                'label_en' => 'Signature',
                'label_th' => 'ลายเซ็นผู้ขอ',
                'field_type' => 'signature',
                'is_required' => true,
                'sort_order' => 6,
                'col_span' => 2,
            ],
        ];

        foreach ($fields as $data) {
            DocumentFormField::create(array_merge(['form_id' => $form->id], $data));
        }

        return $form;
    }

    private function seedPOForm(): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => 'purchase_order_default'],
            [
                'name' => 'ใบสั่งซื้อ',
                'document_type' => 'purchase_order',
                'description' => 'ฟอร์มออกใบสั่งซื้อจากใบขอซื้อที่อนุมัติแล้ว',
                'is_active' => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key' => 'reference_no',
                'label' => 'เลขที่ใบสั่งซื้อ',
                'label_en' => 'PO Number',
                'label_th' => 'เลขที่ใบสั่งซื้อ',
                'field_type' => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 1,
                'col_span' => 2,
            ],
            [
                'field_key' => 'pr_reference',
                'label' => 'อ้างอิงใบขอซื้อ',
                'label_en' => 'PR Reference',
                'label_th' => 'อ้างอิงใบขอซื้อ',
                'field_type' => 'text',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 2,
                'col_span' => 1,
            ],
            [
                'field_key' => 'order_date',
                'label' => 'วันที่สั่งซื้อ',
                'label_en' => 'Order Date',
                'label_th' => 'วันที่สั่งซื้อ',
                'field_type' => 'date',
                'is_required' => true,
                'sort_order' => 3,
                'col_span' => 1,
            ],
            [
                'field_key' => 'supplier',
                'label' => 'ผู้จำหน่าย',
                'label_en' => 'Supplier',
                'label_th' => 'ผู้จำหน่าย',
                'field_type' => 'text',
                'is_required' => true,
                'sort_order' => 4,
                'col_span' => 2,
            ],
            [
                'field_key' => 'delivery_date',
                'label' => 'กำหนดส่งมอบ',
                'label_en' => 'Delivery Date',
                'label_th' => 'กำหนดส่งมอบ',
                'field_type' => 'date',
                'is_required' => false,
                'sort_order' => 5,
                'col_span' => 1,
            ],
            [
                'field_key' => 'payment_terms',
                'label' => 'เงื่อนไขชำระเงิน',
                'label_en' => 'Payment Terms',
                'label_th' => 'เงื่อนไขชำระเงิน',
                'field_type' => 'text',
                'is_required' => false,
                'sort_order' => 6,
                'col_span' => 1,
            ],
            [
                'field_key' => 'notes',
                'label' => 'หมายเหตุ',
                'label_en' => 'Notes',
                'label_th' => 'หมายเหตุ',
                'field_type' => 'textarea',
                'is_required' => false,
                'sort_order' => 7,
                'col_span' => 2,
            ],
            [
                'field_key' => 'signature',
                'label' => 'ลายเซ็นผู้ออก PO',
                'label_en' => 'Signature',
                'label_th' => 'ลายเซ็นผู้ออก PO',
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

    private function seedWorkflow(
        string $name,
        string $documentType,
        string $description,
        string $stageName,
    ): ApprovalWorkflow {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['name' => $name],
            [
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => $stageName,
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
