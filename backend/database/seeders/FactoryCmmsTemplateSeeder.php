<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Factory / CMMS playbook: document types repair_request + pm_am_plan, default forms,
 * single-step approval (MAINT_SUP from FactoryPositionSeeder if seeded, else first position or admin user), global workflow policy.
 *
 * Idempotent (updateOrCreate). Safe to re-run.
 */
class FactoryCmmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'code' => 'repair_request',
                'label_en' => 'Repair request',
                'label_th' => 'แจ้งซ่อม',
                'icon' => 'wrench-screwdriver',
                'sort_order' => 10,
                'routing_mode' => 'hybrid',
            ],
            [
                'code' => 'pm_am_plan',
                'label_en' => 'PM / AM plan',
                'label_th' => 'แผนบำรุงรักษา PM/AM',
                'icon' => 'clipboard-document-list',
                'sort_order' => 11,
                'routing_mode' => 'hybrid',
            ],
        ] as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'label_en' => $type['label_en'],
                    'label_th' => $type['label_th'],
                    'icon' => $type['icon'],
                    'sort_order' => $type['sort_order'],
                    'routing_mode' => $type['routing_mode'],
                    'is_active' => true,
                ]
            );
        }

        $repairForm = $this->syncForm(
            'repair_request_default',
            'แจ้งซ่อม (ค่าเริ่มต้น)',
            'repair_request',
            'เทมเพลตโรงงาน: แจ้งซ่อม — ปรับฟิลด์และ workflow ได้ในเมนูตั้งค่า',
            [
                ['field_key' => 'title', 'label' => 'หัวข้อ', 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 1],
                ['field_key' => 'detail', 'label' => 'รายละเอียดปัญหา', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 2],
                ['field_key' => 'location', 'label' => 'สถานที่ / เครื่องจักร', 'field_type' => 'text', 'is_required' => false, 'is_searchable' => true, 'sort_order' => 3],
            ]
        );

        $pmForm = $this->syncForm(
            'pm_am_plan_default',
            'แผน PM/AM (ค่าเริ่มต้น)',
            'pm_am_plan',
            'เทมเพลตโรงงาน: แผน PM/AM — ฟิลด์ equipment_id ใช้ร่วมกับหน้าสร้างแผน (เลือกจากรายการอุปกรณ์)',
            [
                ['field_key' => 'title', 'label' => 'หัวข้อแผน', 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 1],
                ['field_key' => 'detail', 'label' => 'รายละเอียด', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 2],
                ['field_key' => 'equipment_id', 'label' => 'อุปกรณ์', 'field_type' => 'text', 'is_required' => false, 'is_searchable' => true, 'sort_order' => 3],
            ]
        );

        $stage = $this->defaultApproverStage();

        $repairWf = $this->syncWorkflow('CMMS — อนุมัติแจ้งซ่อม', 'repair_request', 'เทมเพลตโรงงาน (ขั้นเดียว)', [$stage]);
        $pmWf = $this->syncWorkflow('CMMS — อนุมัติแผน PM/AM', 'pm_am_plan', 'เทมเพลตโรงงาน (ขั้นเดียว)', [$stage]);

        $this->syncGlobalPolicy($repairForm, $repairWf);
        $this->syncGlobalPolicy($pmForm, $pmWf);

        $this->command?->info('FactoryCmmsTemplateSeeder: repair_request + pm_am_plan types, default forms, workflows, policies.');
    }

    private function defaultApproverStage(): array
    {
        $pos = Position::query()->where('code', 'MAINT_SUP')->first();
        if ($pos) {
            return [
                'step_no' => 1,
                'name' => 'หัวหน้าช่างอนุมัติ',
                'approver_type' => 'position',
                'approver_ref' => (string) $pos->id,
            ];
        }

        $anyPosition = Position::query()->where('is_active', true)->orderBy('id')->first();
        if ($anyPosition) {
            return [
                'step_no' => 1,
                'name' => 'ผู้ดำเนินการอนุมัติ',
                'approver_type' => 'position',
                'approver_ref' => (string) $anyPosition->id,
            ];
        }

        $admin = User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->orderBy('id')->first();
        if ($admin) {
            return [
                'step_no' => 1,
                'name' => 'ผู้ดำเนินการอนุมัติ',
                'approver_type' => 'user',
                'approver_ref' => (string) $admin->id,
            ];
        }

        throw new \RuntimeException(
            'FactoryCmmsTemplateSeeder: no positions and no users. Run FactoryPositionSeeder and RolePermissionSeeder first.'
        );
    }

    private function syncWorkflow(string $name, string $documentType, string $description, array $stages): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->updateOrCreate(
            ['name' => $name],
            [
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
            ]
        );

        $workflow->stages()->delete();

        foreach ($stages as $stage) {
            ApprovalWorkflowStage::query()->create([
                'workflow_id' => $workflow->id,
                'step_no' => $stage['step_no'],
                'name' => $stage['name'],
                'approver_type' => $stage['approver_type'],
                'approver_ref' => (string) $stage['approver_ref'],
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }

        return $workflow;
    }

    /**
     * @param  array<int, array{field_key: string, label: string, field_type: string, is_required?: bool, is_searchable?: bool, sort_order: int, placeholder?: string|null, options?: array|null}>  $fields
     */
    private function syncForm(string $formKey, string $name, string $documentType, string $description, array $fields): DocumentForm
    {
        // Service-type forms (repair, maintenance, PM) are good candidates for
        // post-action evaluation — enable by default. Non-service types stay off.
        $shouldEnableEval = in_array($documentType, ['repair_request', 'maintenance_request', 'pm_am_plan'], true);

        $form = DocumentForm::query()->updateOrCreate(
            ['form_key' => $formKey],
            [
                'name' => $name,
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
                'evaluation_enabled' => $shouldEnableEval,
                'layout_columns' => 1,
            ]
        );

        foreach ($fields as $f) {
            DocumentFormField::query()->updateOrCreate(
                [
                    'form_id' => $form->id,
                    'field_key' => $f['field_key'],
                ],
                [
                    'label' => $f['label'],
                    'field_type' => $f['field_type'],
                    'is_required' => (bool) ($f['is_required'] ?? false),
                    'is_searchable' => (bool) ($f['is_searchable'] ?? false),
                    'sort_order' => $f['sort_order'],
                    'col_span' => 0,
                    'placeholder' => $f['placeholder'] ?? null,
                    'options' => $f['options'] ?? null,
                ]
            );
        }

        return $form;
    }

    private function syncGlobalPolicy(DocumentForm $form, ApprovalWorkflow $workflow): void
    {
        DocumentFormWorkflowPolicy::query()->updateOrCreate(
            [
                'form_id' => $form->id,
            ],
            [
                'use_amount_condition' => false,
                'workflow_id' => $workflow->id,
            ]
        );
    }
}
