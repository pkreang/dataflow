<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\OrgUnit;
use App\Models\Position;
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
 *   employee@test.com   — submitter (viewer)
 *   manager@test.com    — stage 1 approver (approver)
 *   gm@test.com         — stage 2 approver (approver)
 *   substitute@test.com — substitute for manager (approver)
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
        $viewerRole = Role::where('name', 'viewer')->first();
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
        if ($viewerRole) {
            $employee->syncRoles([$viewerRole]);
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
                ['form_id' => $form->id, 'department_id' => null],
                ['workflow_id' => $wf->id, 'use_amount_condition' => false, 'field_conditions' => []]
            );
        }

        // 9. Substitution: สมหญิง → สมปอง (active, open-ended)
        UserSubstitution::updateOrCreate(
            ['from_user_id' => $manager->id, 'to_user_id' => $substitute->id],
            ['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => null]
        );

        $this->command?->info('UAT Demo ready.');
        $this->command?->info('  employee@test.com   / password  (ผู้ยื่น)');
        $this->command?->info('  manager@test.com    / password  (approver stage 1)');
        $this->command?->info('  substitute@test.com / password  (substitute ของ manager)');
        $this->command?->info('  gm@test.com         / password  (approver stage 2)');
        $this->command?->info('  Form: /forms/leave_request_default/submissions → กรอกแล้ว Submit');
    }
}
