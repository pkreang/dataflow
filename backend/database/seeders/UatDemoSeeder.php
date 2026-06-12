<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSubstitution;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * UAT demo dataset — Leave Request workflow with substitution + escalation.
 *
 * Run after migrate:fresh --seed:
 *   php artisan db:seed --class=UatDemoSeeder
 *
 * Test accounts (password: password):
 *   employee@test.com   — submitter (employee)
 *   manager@test.com    — stage 1 approver (approver)
 *   gm@test.com         — stage 2 approver (approver)
 *   substitute@test.com — substitute for manager (approver)
 *   hr@test.com         — HR approver for the quorum variant (approver)
 *
 * Leave form variants (one per workflow style, pick from sidebar):
 *   leave_request_default  — 2-stage user-based + escalation + substitution
 *   leave_request_position — 2-stage position-based (หัวหน้าฝ่าย → ผู้จัดการ)
 *   leave_request_manager  — direct manager from users.manager_id → gm
 *   leave_request_by_days  — total_days <= 2 จบที่หัวหน้า, > 2 ต่อถึง gm
 *   leave_request_override — requester picks stage-1 approver (override)
 *   leave_request_quorum   — single stage needs หัวหน้าฝ่าย AND HR
 */
class UatDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Leave Request form + default lookup + running number
        $this->call(LeaveRequestTemplateSeeder::class);

        // 2. Company + Branch
        $company = Company::updateOrCreate(
            ['code' => 'UAT-SCH'],
            ['name' => 'โรงเรียนทดสอบ UAT', 'tax_id' => '0000000000000', 'is_active' => true]
        );

        $branch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'UAT-SCH-HQ'],
            ['name' => 'สาขาหลัก', 'is_active' => true]
        );

        // 3. Departments
        $deptAcad = Department::updateOrCreate(
            ['code' => 'UAT_ACAD'],
            ['name' => 'ฝ่ายวิชาการ', 'is_active' => true]
        );
        $deptAdmin = Department::updateOrCreate(
            ['code' => 'UAT_ADMIN'],
            ['name' => 'ฝ่ายธุรการ', 'is_active' => true]
        );

        // 4. Positions
        $posTeacher = Position::updateOrCreate(
            ['code' => 'UAT_TEACHER'],
            ['name' => 'ครู', 'is_active' => true]
        );
        $posHead = Position::updateOrCreate(
            ['code' => 'UAT_HEAD'],
            ['name' => 'หัวหน้าฝ่าย', 'is_active' => true]
        );
        $posMgr = Position::updateOrCreate(
            ['code' => 'UAT_MGR'],
            ['name' => 'ผู้จัดการ', 'is_active' => true]
        );

        // 5. Users
        $employeeRole = Role::where('name', 'employee')->first();
        $approverRole = Role::where('name', 'approver')->first();

        $employee = User::updateOrCreate(
            ['email' => 'employee@test.com'],
            [
                'first_name' => 'สมชาย',
                'last_name' => 'ใจดี',
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'department_id' => $deptAcad->id,
                'position_id' => $posTeacher->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($employeeRole) {
            $employee->syncRoles([$employeeRole]);
        }

        $manager = User::updateOrCreate(
            ['email' => 'manager@test.com'],
            [
                'first_name' => 'สมหญิง',
                'last_name' => 'รักเรียน',
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'department_id' => $deptAcad->id,
                'position_id' => $posHead->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($approverRole) {
            $manager->syncRoles([$approverRole]);
        }

        $gm = User::updateOrCreate(
            ['email' => 'gm@test.com'],
            [
                'first_name' => 'สมศรี',
                'last_name' => 'บริหาร',
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'department_id' => $deptAdmin->id,
                'position_id' => $posMgr->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($approverRole) {
            $gm->syncRoles([$approverRole]);
        }

        $substitute = User::updateOrCreate(
            ['email' => 'substitute@test.com'],
            [
                'first_name' => 'สมปอง',
                'last_name' => 'แทนงาน',
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'department_id' => $deptAcad->id,
                'position_id' => $posTeacher->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
        if ($approverRole) {
            $substitute->syncRoles([$approverRole]);
        }

        // 6. OrgUnits
        $root = OrgUnit::updateOrCreate(
            ['name' => 'โรงเรียนทดสอบ UAT'],
            ['type' => 'company', 'parent_id' => null, 'head_user_id' => $gm->id, 'sort_order' => 1, 'is_active' => true]
        );
        $ouAcad = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายวิชาการ (UAT)'],
            ['type' => 'department', 'parent_id' => $root->id, 'head_user_id' => $manager->id, 'sort_order' => 1, 'is_active' => true]
        );
        OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายธุรการ (UAT)'],
            ['type' => 'department', 'parent_id' => $root->id, 'head_user_id' => null, 'sort_order' => 2, 'is_active' => true]
        );

        User::where('email', 'employee@test.com')->update(['org_unit_id' => $ouAcad->id]);
        User::where('email', 'manager@test.com')->update(['org_unit_id' => $ouAcad->id]);
        User::where('email', 'substitute@test.com')->update(['org_unit_id' => $ouAcad->id]);
        User::where('email', 'gm@test.com')->update(['org_unit_id' => $root->id]);

        // 7. Approval Workflow — 2 stages, user-based, with escalation
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลา 2 ขั้น (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wf->stages()->delete();

        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id,
            'step_no' => 1,
            'name' => 'หัวหน้าฝ่าย',
            'approver_type' => 'user',
            'approver_ref' => (string) $manager->id,
            'min_approvals' => 1,
            'escalation_after_days' => 2,
            'is_active' => true,
        ]);

        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id,
            'step_no' => 2,
            'name' => 'ผู้จัดการ',
            'approver_type' => 'user',
            'approver_ref' => (string) $gm->id,
            'min_approvals' => 1,
            'escalation_after_days' => 3,
            'is_active' => true,
        ]);

        // 8. Bind UAT workflow to leave_request_default form
        $form = DocumentForm::where('form_key', 'leave_request_default')->first();
        if ($form) {
            DocumentFormWorkflowPolicy::updateOrCreate(
                ['form_id' => $form->id, 'department_id' => null, 'position_id' => null],
                ['workflow_id' => $wf->id, 'use_amount_condition' => false, 'field_conditions' => []]
            );
        }

        // 9. Substitution: สมหญิง → สมปอง (active, open-ended)
        UserSubstitution::updateOrCreate(
            ['from_user_id' => $manager->id, 'to_user_id' => $substitute->id],
            ['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => null]
        );

        // 10. Workflow variants — ฟอร์มใบลาแยก 1 ฟอร์มต่อ 1 รูปแบบ routing
        if ($form) {
            $this->seedWorkflowVariants($form, $deptAdmin, $posTeacher, $posHead, $posMgr, $employee, $manager, $gm, $substitute, $company, $branch, $root, $wf);
        }

        $this->command?->info('UAT Demo ready.');
        $this->command?->info('  employee@test.com   / password  (ผู้ยื่น)');
        $this->command?->info('  manager@test.com    / password  (approver stage 1)');
        $this->command?->info('  substitute@test.com / password  (substitute ของ manager)');
        $this->command?->info('  gm@test.com         / password  (approver stage 2)');
        $this->command?->info('  hr@test.com         / password  (HR สำหรับ quorum)');
        $this->command?->info('  Forms (เลือกจาก sidebar เอกสาร > ใบลา):');
        $this->command?->info('    ใบลา                      — user-based 2 ขั้น + substitution + escalation');
        $this->command?->info('    ใบลา (ตามตำแหน่ง)          — position-based 2 ขั้น');
        $this->command?->info('    ใบลา (ตามสายบังคับบัญชา)   — direct manager → gm');
        $this->command?->info('    ใบลา (ตามจำนวนวัน)         — <=2 วันจบที่หัวหน้า, >2 วันถึง gm');
        $this->command?->info('    ใบลา (เลือกผู้อนุมัติเอง)   — requester เลือก approver ขั้น 1 ได้');
        $this->command?->info('    ใบลา (หัวหน้า + HR)        — ขั้นเดียวต้องครบทั้งสองฝ่าย');
    }

    /**
     * สร้าง workflow + ฟอร์มใบลาอีก 5 รูปแบบ ให้ผู้ทดสอบเลือกฟอร์มจากเมนู
     * แล้วเห็นพฤติกรรม routing แต่ละแบบโดยไม่ปนกัน
     */
    private function seedWorkflowVariants(
        DocumentForm $baseForm,
        Department $deptAdmin,
        Position $posTeacher,
        Position $posHead,
        Position $posMgr,
        User $employee,
        User $manager,
        User $gm,
        User $substitute,
        Company $company,
        Branch $branch,
        OrgUnit $root,
        ApprovalWorkflow $wfTwoStage,
    ): void {
        $approverRole = Role::where('name', 'approver')->first();

        // HR position + user (ใช้ใน quorum variant)
        $posHr = Position::updateOrCreate(
            ['code' => 'UAT_HR'],
            ['name' => 'เจ้าหน้าที่บุคคล', 'is_active' => true]
        );
        $hr = User::updateOrCreate(
            ['email' => 'hr@test.com'],
            [
                'first_name' => 'สมคิด',
                'last_name' => 'ดูแลคน',
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'department_id' => $deptAdmin->id,
                'position_id' => $posHr->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'org_unit_id' => $root->id,
                'is_active' => true,
            ]
        );
        if ($approverRole) {
            $hr->syncRoles([$approverRole]);
        }
        // HR ยื่นเอกสารแทนพนักงานคนอื่นได้ (UAT ฟีเจอร์ on-behalf)
        if (\Spatie\Permission\Models\Permission::where('name', 'submission.create_for_others')->exists()) {
            $hr->givePermissionTo('submission.create_for_others');
        }

        // สายบังคับบัญชาสำหรับ direct_manager (users.manager_id)
        $employee->update(['manager_id' => $manager->id]);
        $substitute->update(['manager_id' => $manager->id]);
        $manager->update(['manager_id' => $gm->id]);

        // ---- (1) position-based: ตำแหน่งหัวหน้าฝ่าย → ตำแหน่งผู้จัดการ ----
        $wfPosition = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลาตามตำแหน่ง (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wfPosition->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfPosition->id, 'step_no' => 1, 'name' => 'หัวหน้าฝ่าย (ตำแหน่ง)',
            'approver_type' => 'position', 'approver_ref' => (string) $posHead->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfPosition->id, 'step_no' => 2, 'name' => 'ผู้จัดการ (ตำแหน่ง)',
            'approver_type' => 'position', 'approver_ref' => (string) $posMgr->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        $this->bindVariantForm($baseForm, 'leave_request_position', 'ใบลา (ตามตำแหน่ง)', $wfPosition);

        // ---- (2) direct manager: หัวหน้าตาม users.manager_id → gm ----
        $wfManager = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลาตามสายบังคับบัญชา (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wfManager->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfManager->id, 'step_no' => 1, 'name' => 'หัวหน้าโดยตรง',
            'approver_type' => 'direct_manager', 'approver_ref' => '',
            'min_approvals' => 1, 'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfManager->id, 'step_no' => 2, 'name' => 'ผู้จัดการ',
            'approver_type' => 'user', 'approver_ref' => (string) $gm->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        $this->bindVariantForm($baseForm, 'leave_request_manager', 'ใบลา (ตามสายบังคับบัญชา)', $wfManager);

        // ---- (3) ตามจำนวนวัน: <=2 วันจบที่หัวหน้า, >2 วันใช้ 2 ขั้นถึง gm ----
        $wfShort = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลาระยะสั้น (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wfShort->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfShort->id, 'step_no' => 1, 'name' => 'หัวหน้าฝ่าย',
            'approver_type' => 'position', 'approver_ref' => (string) $posHead->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        $formByDays = $this->bindVariantForm($baseForm, 'leave_request_by_days', 'ใบลา (ตามจำนวนวัน)', $wfShort);
        // field condition ชนะ default: total_days > 2 → workflow 2 ขั้น (ดู badge "ขั้นสูง" ในหน้า routing)
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $formByDays->id, 'department_id' => null, 'position_id' => null],
            [
                'workflow_id' => $wfShort->id,
                'use_amount_condition' => false,
                'field_conditions' => [
                    ['field_key' => 'total_days', 'operator' => '>', 'value' => 2, 'workflow_id' => $wfTwoStage->id, 'priority' => 1],
                ],
            ]
        );

        // ---- (4) requester override: ผู้ยื่นเลือก approver ขั้น 1 เองได้ ----
        Setting::set('approval.allow_requester_override', true);
        $wfOverride = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลาเลือกผู้อนุมัติเอง (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wfOverride->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfOverride->id, 'step_no' => 1, 'name' => 'ผู้อนุมัติที่ผู้ยื่นเลือก',
            'approver_type' => 'user', 'approver_ref' => (string) $manager->id,
            'allow_requester_override' => true,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfOverride->id, 'step_no' => 2, 'name' => 'ผู้จัดการ',
            'approver_type' => 'user', 'approver_ref' => (string) $gm->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        $this->bindVariantForm($baseForm, 'leave_request_override', 'ใบลา (เลือกผู้อนุมัติเอง)', $wfOverride);

        // ---- (5) quorum: ขั้นเดียว ต้องได้ทั้งหัวหน้าฝ่าย และ HR (AND) ----
        $wfQuorum = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลาหัวหน้าและ HR (UAT)'],
            ['document_type' => 'leave_request', 'is_active' => true]
        );
        $wfQuorum->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfQuorum->id, 'step_no' => 1, 'name' => 'หัวหน้าฝ่าย + HR',
            'approver_type' => 'position', 'approver_ref' => (string) $posHead->id,
            'approver_rules' => [
                ['type' => 'position', 'ref' => (string) $posHr->id, 'min_count' => 1],
            ],
            'min_approvals' => 2, // ทั้งสอง source ต้องครบ
            'is_active' => true,
        ]);
        $this->bindVariantForm($baseForm, 'leave_request_quorum', 'ใบลา (หัวหน้า + HR)', $wfQuorum);
    }

    /**
     * Clone ฟอร์มใบลา base เป็นฟอร์มใหม่ + ผูก global policy เข้า workflow ที่ให้มา
     */
    private function bindVariantForm(DocumentForm $baseForm, string $formKey, string $name, ApprovalWorkflow $workflow): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => $formKey],
            [
                'name' => $name,
                'document_type' => $baseForm->document_type,
                'description' => $baseForm->description,
                'is_active' => true,
                'layout_columns' => $baseForm->layout_columns,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();
        foreach ($baseForm->fields()->orderBy('sort_order')->get() as $field) {
            $copy = $field->replicate();
            $copy->form_id = $form->id;
            $copy->save();
        }

        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id, 'department_id' => null, 'position_id' => null],
            ['workflow_id' => $workflow->id, 'use_amount_condition' => false, 'field_conditions' => []]
        );

        return $form;
    }
}
