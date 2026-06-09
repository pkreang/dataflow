<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * School playbook: sample departments (document types from DocumentTypeSeeder),
 * three starter forms (leave, small procurement, activity), approval workflow by position,
 * organization-wide routing + global policy (no amount routing).
 * Purges legacy factory departments (MAINT, PROD, WH, …), removes non-school document forms,
 * then seeds SCH_* ฝ่าย only.
 * When SCH_ACAD_HEAD + SCH_VICE_PRINCIPAL positions exist (PositionDemoSeeder), workflows use two steps;
 * otherwise falls back to a single position or admin user — not Spatie roles.
 *
 * Forms appear under /forms. Idempotent.
 */
class SchoolEFormTemplateSeeder extends Seeder
{
    /** Must match DocumentTypeSeeder school codes. */
    private const SCHOOL_DOCUMENT_TYPE_CODES = [
        'school_leave_request',
        'school_procurement',
        'school_activity',
    ];

    public function run(): void
    {
        DepartmentSeeder::purgeLegacyFactoryDepartments();

        DocumentForm::query()
            ->whereNotIn('document_type', self::SCHOOL_DOCUMENT_TYPE_CODES)
            ->delete();

        ApprovalWorkflow::query()
            ->whereNotIn('document_type', self::SCHOOL_DOCUMENT_TYPE_CODES)
            ->delete();

        $this->seedCompany();
        $this->seedDepartments();
        $this->seedDirectorPosition();

        // Ensure the shared `leave_type` lookup list exists (idempotent — same key
        // as BodindechaDemoSeeder so running either seeder is safe).
        $leaveList = LookupList::updateOrCreate(
            ['key' => 'leave_type'],
            ['label_en' => 'Leave Type', 'label_th' => 'ประเภทการลา', 'description' => 'ประเภทการลา — ใช้ร่วมกันทั้ง bd_leave และ school_leave_default', 'is_system' => false, 'is_active' => true, 'sort_order' => 20]
        );
        foreach ([
            ['value' => 'sick',       'label_en' => 'Sick Leave',       'label_th' => 'ลาป่วย',     'sort_order' => 1],
            ['value' => 'personal',   'label_en' => 'Personal Leave',   'label_th' => 'ลากิจ',      'sort_order' => 2],
            ['value' => 'vacation',   'label_en' => 'Vacation',         'label_th' => 'ลาพักผ่อน',  'sort_order' => 3],
            ['value' => 'maternity',  'label_en' => 'Maternity Leave',  'label_th' => 'ลาคลอด',     'sort_order' => 4],
            ['value' => 'ordination', 'label_en' => 'Ordination Leave', 'label_th' => 'ลาอุปสมบท',  'sort_order' => 5],
            ['value' => 'other',      'label_en' => 'Other',            'label_th' => 'อื่นๆ',      'sort_order' => 6],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $leaveList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $leaveFields = $this->leaveFormFields();

        $leaveForm = $this->syncForm(
            'school_leave_default',
            'คำขอลา (ครู/บุคลากร)',
            'school_leave_request',
            'เทมเพลตโรงเรียน tier 1: หัวหน้าฝ่าย → รองผอ.',
            $leaveFields
        );

        $procForm = $this->syncForm(
            'school_procurement_default',
            'ขอซื้อจัดหา (ตัวอย่าง)',
            'school_procurement',
            'เทมเพลตโรงเรียน: วงเงินประมาณสำหรับอ้างอิง (ยังไม่ผูก routing ตามวงเงิน)',
            [
                ['field_key' => 'title', 'label' => 'เรื่อง', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['field_key' => 'detail', 'label' => 'รายละเอียดสิ่งของหรืองาน', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 2],
                ['field_key' => 'estimated_amount', 'label' => 'งบประมาณโดยประมาณ (บาท)', 'field_type' => 'number', 'is_required' => false, 'sort_order' => 3],
            ]
        );

        $actForm = $this->syncForm(
            'school_activity_default',
            'ขออนุมัติกิจกรรม (ตัวอย่าง)',
            'school_activity',
            'เทมเพลตโรงเรียน: กิจกรรมนอกสถานที่ / ภายในโรงเรียน',
            [
                ['field_key' => 'title', 'label' => 'ชื่อกิจกรรม', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['field_key' => 'event_date', 'label' => 'วันที่จัด', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 2],
                ['field_key' => 'venue', 'label' => 'สถานที่', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 3],
                ['field_key' => 'detail', 'label' => 'รายละเอียด', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 4],
            ]
        );

        $stages = $this->schoolApprovalStages();
        $flowHint = count($stages) > 1
            ? 'เทมเพลตโรงเรียน: หัวหน้าฝ่ายวิชาการ → รองผู้อำนวยการ'
            : 'เทมเพลตโรงเรียน: ขั้นเดียวตามตำแหน่งหรือผู้ใช้ (ไม่ผูกกับ role approver)';

        $wLeave = $this->syncWorkflow('โรงเรียน — อนุมัติการลา', 'school_leave_request', $flowHint, $stages);
        $wProc = $this->syncWorkflow('โรงเรียน — อนุมัติขอซื้อ', 'school_procurement', $flowHint, $stages);
        $wAct = $this->syncWorkflow('โรงเรียน — อนุมัติกิจกรรม', 'school_activity', $flowHint, $stages);

        $this->syncGlobalPolicy($leaveForm, $wLeave);
        $this->syncGlobalPolicy($procForm, $wProc);
        $this->syncGlobalPolicy($actForm, $wAct);

        // tier 2: หัวหน้าฝ่าย → รองผอ. → ผอ.
        $leaveHeadForm = $this->syncForm(
            'school_leave_head_default',
            'ใบลา (หัวหน้าฝ่าย)',
            'school_leave_request',
            'เทมเพลตโรงเรียน tier 2: รองผอ. → ผอ.',
            $leaveFields
        );
        $wLeaveHead = $this->syncWorkflow(
            'โรงเรียน — อนุมัติการลา (หัวหน้าฝ่าย)',
            'school_leave_request',
            'เทมเพลตโรงเรียน tier 2: รองผู้อำนวยการ → ผู้อำนวยการ',
            $this->headApprovalStages()
        );
        $this->syncGlobalPolicy($leaveHeadForm, $wLeaveHead);

        // tier 3: รองผอ./ผอ. → ผอ. อย่างเดียว
        $leaveExecForm = $this->syncForm(
            'school_leave_exec_default',
            'ใบลา (ผู้บริหาร)',
            'school_leave_request',
            'เทมเพลตโรงเรียน tier 3: ผู้อำนวยการอนุมัติ',
            $leaveFields
        );
        $wLeaveExec = $this->syncWorkflow(
            'โรงเรียน — อนุมัติการลา (ผู้บริหาร)',
            'school_leave_request',
            'เทมเพลตโรงเรียน tier 3: ผู้อำนวยการอนุมัติ',
            $this->execApprovalStages()
        );
        $this->syncGlobalPolicy($leaveExecForm, $wLeaveExec);

        $this->command?->info('SchoolEFormTemplateSeeder: school departments, forms, workflows, policies.');
    }

    /**
     * @return array<int, array{step_no: int, name: string, approver_type: string, approver_ref: string}>
     */
    private function schoolApprovalStages(): array
    {
        $head = Position::query()->where('code', 'SCH_ACAD_HEAD')->first();
        $vice = Position::query()->where('code', 'SCH_VICE_PRINCIPAL')->first();

        if ($head && $vice) {
            return [
                [
                    'step_no' => 1,
                    'name' => 'หัวหน้าฝ่ายวิชาการอนุมัติ',
                    'approver_type' => 'position',
                    'approver_ref' => (string) $head->id,
                ],
                [
                    'step_no' => 2,
                    'name' => 'รองผู้อำนวยการอนุมัติ',
                    'approver_type' => 'position',
                    'approver_ref' => (string) $vice->id,
                ],
            ];
        }

        $anyPosition = Position::query()->where('is_active', true)->orderBy('id')->first();
        if ($anyPosition) {
            return [
                [
                    'step_no' => 1,
                    'name' => 'ผู้ดำเนินการอนุมัติ',
                    'approver_type' => 'position',
                    'approver_ref' => (string) $anyPosition->id,
                ],
            ];
        }

        $admin = \App\Models\User::query()->where('email', 'admin@example.com')->first()
            ?? \App\Models\User::query()->orderBy('id')->first();
        if ($admin) {
            return [
                [
                    'step_no' => 1,
                    'name' => 'ผู้ดำเนินการอนุมัติ',
                    'approver_type' => 'user',
                    'approver_ref' => (string) $admin->id,
                ],
            ];
        }

        throw new \RuntimeException(
            'SchoolEFormTemplateSeeder: no positions and no users. Run PositionDemoSeeder and RolePermissionSeeder first.'
        );
    }

    private function seedDepartments(): void
    {
        $rows = [
            ['code' => 'SCH_ACAD', 'name' => 'ฝ่ายวิชาการ', 'description' => 'การเรียนการสอน — ผู้ยื่น eForm ส่วนใหญ่'],
            ['code' => 'SCH_ADM', 'name' => 'ฝ่ายธุรการ', 'description' => 'ประสานงาน ธุรการ'],
            ['code' => 'SCH_FIN', 'name' => 'ฝ่ายการเงิน', 'description' => 'งบประมาณ การเงิน'],
            ['code' => 'SCH_FAC', 'name' => 'ฝ่ายอาคารและสถานที่', 'description' => 'อาคาร สนาม สาธารณูปโภค'],
        ];

        foreach ($rows as $row) {
            Department::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => true,
                ]
            );
        }
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
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function syncForm(string $formKey, string $name, string $documentType, string $description, array $fields): DocumentForm
    {
        $form = DocumentForm::query()->updateOrCreate(
            ['form_key' => $formKey],
            [
                'name' => $name,
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
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
                'department_id' => null,
            ],
            [
                'use_amount_condition' => false,
                'workflow_id' => $workflow->id,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function leaveFormFields(): array
    {
        return [
            ['field_key' => 'title',      'label' => 'หัวเรื่อง',                'field_type' => 'text',     'is_required' => true,  'sort_order' => 1],
            ['field_key' => 'leave_type', 'label' => 'ประเภทการลา',             'field_type' => 'lookup',   'is_required' => true,  'sort_order' => 2, 'options' => ['source' => 'leave_type']],
            ['field_key' => 'start_date', 'label' => 'วันเริ่ม',                'field_type' => 'date',     'is_required' => true,  'sort_order' => 3],
            ['field_key' => 'end_date',   'label' => 'วันสิ้นสุด',              'field_type' => 'date',     'is_required' => true,  'sort_order' => 4],
            ['field_key' => 'detail',     'label' => 'เหตุผล / รายละเอียด',    'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 5],
        ];
    }

    private function seedDirectorPosition(): void
    {
        Position::query()->updateOrCreate(
            ['code' => 'SCH_DIRECTOR'],
            ['name' => 'ผู้อำนวยการ', 'is_active' => true]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function headApprovalStages(): array
    {
        $vice     = Position::query()->where('code', 'SCH_VICE_PRINCIPAL')->first();
        $director = Position::query()->where('code', 'SCH_DIRECTOR')->first();

        if ($vice && $director) {
            return [
                ['step_no' => 1, 'name' => 'รองผู้อำนวยการอนุมัติ', 'approver_type' => 'position', 'approver_ref' => (string) $vice->id],
                ['step_no' => 2, 'name' => 'ผู้อำนวยการอนุมัติ',    'approver_type' => 'position', 'approver_ref' => (string) $director->id],
            ];
        }

        return $this->schoolApprovalStages();
    }

    /** @return array<int, array<string, mixed>> */
    private function execApprovalStages(): array
    {
        $director = Position::query()->where('code', 'SCH_DIRECTOR')->first();

        if ($director) {
            return [
                ['step_no' => 1, 'name' => 'ผู้อำนวยการอนุมัติ', 'approver_type' => 'position', 'approver_ref' => (string) $director->id],
            ];
        }

        return $this->schoolApprovalStages();
    }

    private function seedCompany(): void
    {
        $company = \App\Models\Company::updateOrCreate(
            ['code' => 'SCH_DEMO'],
            [
                'name'      => 'โรงเรียนสาธิต (Demo)',
                'tax_id'    => '0000000000000',
                'is_active' => true,
            ]
        );

        \App\Models\Branch::updateOrCreate(
            ['code' => 'SCH_MAIN'],
            [
                'company_id' => $company->id,
                'name'       => 'สาขาหลัก',
                'is_active'  => true,
            ]
        );
    }
}
