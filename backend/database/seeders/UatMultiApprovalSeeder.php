<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentFormWorkflowRange;
use App\Models\DocumentType;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\RunningNumberConfig;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UatMultiApprovalSeeder extends Seeder
{
    public function run(): void
    {
        // ---- 0. Clear old UAT data ----------------------------------------
        DB::table('document_form_submissions')->delete();
        DB::table('approval_instance_steps')->delete();
        DB::table('approval_instances')->delete();
        RunningNumberConfig::where('document_type', 'repair_request')->update(['last_number' => 0]);

        // ---- 1. Roles & permissions ----------------------------------------
        $this->ensureApprovalPermission();
        $employeeRole = Role::firstOrCreate(['name' => 'employee',   'guard_name' => 'web']);
        $approverRole = Role::firstOrCreate(['name' => 'approver', 'guard_name' => 'web']);
        $approverRole->givePermissionTo('approval.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ---- 2. Document types ---------------------------------------------
        DocumentType::updateOrCreate(['code' => 'leave_request'], [
            'label_en' => 'Leave Request',
            'label_th' => 'ใบลา / ขออนุญาตหยุด',
            'icon' => 'calendar-days',
            'routing_mode' => 'hybrid',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        DocumentType::updateOrCreate(['code' => 'purchase_request'], [
            'label_en' => 'Purchase Request',
            'label_th' => 'ใบสั่งซื้อ',
            'icon' => 'shopping-cart',
            'routing_mode' => 'hybrid',
            'is_active' => true,
            'sort_order' => 11,
        ]);

        // ---- 3. Org units (HR / ACC / IT as departments under a root) ------
        $rootOrg = OrgUnit::firstOrCreate(
            ['name' => 'บริษัท ABC (UAT)', 'parent_id' => null],
            ['type' => 'company', 'is_active' => true]
        );
        $hrOrg = OrgUnit::firstOrCreate(
            ['name' => 'ฝ่ายบุคคล (HR)', 'parent_id' => $rootOrg->id],
            ['type' => 'department', 'is_active' => true]
        );
        $acctOrg = OrgUnit::firstOrCreate(
            ['name' => 'ฝ่ายบัญชี (ACC)', 'parent_id' => $rootOrg->id],
            ['type' => 'department', 'is_active' => true]
        );
        $itOrg = OrgUnit::firstOrCreate(
            ['name' => 'ฝ่ายไอที (IT)', 'parent_id' => $rootOrg->id],
            ['type' => 'department', 'is_active' => true]
        );

        // ---- 4. Positions --------------------------------------------------
        $hrSupPos = Position::updateOrCreate(['code' => 'HR_SUP'], ['name' => 'หัวหน้าฝ่ายบุคคล',  'is_active' => true]);
        $acctMgrPos = Position::updateOrCreate(['code' => 'ACCT_MGR'], ['name' => 'ผู้จัดการบัญชี',     'is_active' => true]);
        $mgrPos = Position::where('code', 'MGR')->firstOrFail();   // existing #2 (somsri)
        $supPos = Position::where('code', 'SUP')->firstOrFail();   // existing #4 (somying)

        // ---- 5. Users (password: Test@1234) --------------------------------
        $password = Hash::make('Test@1234');

        // HR submitter
        $hrStaff = $this->makeUser('hr.staff@abc.co.th', 'สมหมาย', 'ใจดี', $hrOrg->id, null, $employeeRole, $password);

        // HR supervisors (3 คน, position HR_SUP → quorum 2/3 step 1)
        $hrSup1 = $this->makeUser('hr.sup1@abc.co.th', 'มานะ', 'รักงาน', $hrOrg->id, $hrSupPos->id, $approverRole, $password);
        $hrSup2 = $this->makeUser('hr.sup2@abc.co.th', 'มานิ', 'ขยัน', $hrOrg->id, $hrSupPos->id, $approverRole, $password);
        $hrSup3 = $this->makeUser('hr.sup3@abc.co.th', 'มาลี', 'สุขใจ', $hrOrg->id, $hrSupPos->id, $approverRole, $password);

        // Accounting submitter
        $acctStaff = $this->makeUser('acct.staff@abc.co.th', 'บัญชา', 'ตรงไป', $acctOrg->id, null, $employeeRole, $password);

        // Accounting managers (3 คน, position ACCT_MGR → quorum 2/3 step 1)
        $acctMgr1 = $this->makeUser('acct.mgr1@abc.co.th', 'ชาญ', 'บัญชีดี', $acctOrg->id, $acctMgrPos->id, $approverRole, $password);
        $acctMgr2 = $this->makeUser('acct.mgr2@abc.co.th', 'ชาลี', 'เก่งเลข', $acctOrg->id, $acctMgrPos->id, $approverRole, $password);
        $acctMgr3 = $this->makeUser('acct.mgr3@abc.co.th', 'ชัย', 'รักเลข', $acctOrg->id, $acctMgrPos->id, $approverRole, $password);

        // IT extra supervisors (เพิ่มเข้า position SUP สำหรับ quorum repair_request)
        $itHead2 = $this->makeUser('it.head2@abc.co.th', 'สมเกียรติ', 'ดีงาม', $itOrg->id, $supPos->id, $approverRole, $password);
        $itHead3 = $this->makeUser('it.head3@abc.co.th', 'สมภพ', 'สุดยอด', $itOrg->id, $supPos->id, $approverRole, $password);

        // Grant approval.approve to all approver users
        foreach ([$hrSup1, $hrSup2, $hrSup3, $acctMgr1, $acctMgr2, $acctMgr3, $itHead2, $itHead3] as $u) {
            $u->givePermissionTo('approval.approve');
        }
        // somsri already has approver role — ensure permission
        $somsri = User::where('email', 'somsri@abc.co.th')->first();
        if ($somsri) {
            $somsri->assignRole($approverRole);
            $somsri->givePermissionTo('approval.approve');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ---- 6. Workflows --------------------------------------------------

        // 6a. WF-Leave (ใบลา, 2 ขั้น)
        $wfLeave = ApprovalWorkflow::create([
            'name' => 'ใบลา 2 ขั้น (quorum)',
            'document_type' => 'leave_request',
            'description' => 'Step 1: หัวหน้าฝ่ายบุคคล 2/3, Step 2: ผู้จัดการ (override)',
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfLeave->id,
            'step_no' => 1,
            'name' => 'อนุมัติหัวหน้าฝ่ายบุคคล',
            'approver_type' => 'position',
            'approver_ref' => (string) $hrSupPos->id,
            'min_approvals' => 2,
            'is_active' => true,
            'allow_requester_override' => false,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfLeave->id,
            'step_no' => 2,
            'name' => 'อนุมัติผู้จัดการ',
            'approver_type' => 'position',
            'approver_ref' => (string) $mgrPos->id,
            'min_approvals' => 1,
            'is_active' => true,
            'allow_requester_override' => true,
        ]);

        // 6b. WF-Purchase-Small (≤ 50,000, 1 ขั้น)
        $wfPurchaseSmall = ApprovalWorkflow::create([
            'name' => 'ใบสั่งซื้อ ≤50,000 (1 ขั้น)',
            'document_type' => 'purchase_request',
            'description' => 'วงเงินไม่เกิน 50,000 บาท',
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfPurchaseSmall->id,
            'step_no' => 1,
            'name' => 'อนุมัติผู้จัดการบัญชี',
            'approver_type' => 'position',
            'approver_ref' => (string) $acctMgrPos->id,
            'min_approvals' => 2,
            'is_active' => true,
            'allow_requester_override' => false,
        ]);

        // 6c. WF-Purchase-Large (> 50,000, 2 ขั้น)
        $wfPurchaseLarge = ApprovalWorkflow::create([
            'name' => 'ใบสั่งซื้อ >50,000 (2 ขั้น)',
            'document_type' => 'purchase_request',
            'description' => 'วงเงินมากกว่า 50,000 บาท',
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfPurchaseLarge->id,
            'step_no' => 1,
            'name' => 'อนุมัติผู้จัดการบัญชี',
            'approver_type' => 'position',
            'approver_ref' => (string) $acctMgrPos->id,
            'min_approvals' => 2,
            'is_active' => true,
            'allow_requester_override' => false,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfPurchaseLarge->id,
            'step_no' => 2,
            'name' => 'อนุมัติผู้จัดการ',
            'approver_type' => 'position',
            'approver_ref' => (string) $mgrPos->id,
            'min_approvals' => 1,
            'is_active' => true,
            'allow_requester_override' => true,
        ]);

        // 6d. Update WF #1 (repair_request): step1 min_approvals=2, step2 allow_requester_override=true
        $wf1Step1 = ApprovalWorkflowStage::where('workflow_id', 1)->where('step_no', 1)->first();
        if ($wf1Step1) {
            $wf1Step1->update(['min_approvals' => 2]);
        }
        $wf1Step2 = ApprovalWorkflowStage::where('workflow_id', 1)->where('step_no', 2)->first();
        if ($wf1Step2) {
            $wf1Step2->update(['allow_requester_override' => true]);
        }

        // ---- 7. Document Forms --------------------------------------------

        // 7a. Leave Request Form
        $leaveForm = DocumentForm::updateOrCreate(
            ['form_key' => 'leave_request_default'],
            [
                'name' => 'ใบลา',
                'document_type' => 'leave_request',
                'description' => 'แบบฟอร์มขออนุญาตหยุด / ลา',
                'is_active' => true,
                'layout_columns' => 2,
            ]
        );
        $leaveForm->fields()->delete();
        $leaveFields = [
            ['field_key' => 'leave_type', 'label' => 'ประเภทการลา', 'field_type' => 'select', 'is_required' => true,  'sort_order' => 1, 'options' => json_encode(['sick' => 'ลาป่วย', 'personal' => 'ลากิจ', 'vacation' => 'ลาพักผ่อน', 'maternity' => 'ลาคลอด', 'other' => 'อื่นๆ'])],
            ['field_key' => 'start_date', 'label' => 'วันเริ่มลา',  'field_type' => 'date',   'is_required' => true,  'sort_order' => 2, 'options' => null],
            ['field_key' => 'end_date',   'label' => 'วันสิ้นสุด',  'field_type' => 'date',   'is_required' => true,  'sort_order' => 3, 'options' => null],
            ['field_key' => 'reason',     'label' => 'เหตุผล',       'field_type' => 'textarea', 'is_required' => true,  'sort_order' => 4, 'options' => null],
        ];
        foreach ($leaveFields as $f) {
            DocumentFormField::create(['form_id' => $leaveForm->id] + $f);
        }
        // restrict to HR org unit
        $leaveForm->orgUnits()->sync([$hrOrg->id]);

        // 7b. Purchase Request Form
        $purchaseForm = DocumentForm::updateOrCreate(
            ['form_key' => 'purchase_request_default'],
            [
                'name' => 'ใบสั่งซื้อ',
                'document_type' => 'purchase_request',
                'description' => 'แบบฟอร์มใบสั่งซื้อ / จัดซื้อ',
                'is_active' => true,
                'layout_columns' => 1,
            ]
        );
        $purchaseForm->fields()->delete();
        $purchaseFields = [
            ['field_key' => 'title',        'label' => 'เรื่อง / วัตถุประสงค์',  'field_type' => 'text',    'is_required' => true,  'sort_order' => 1, 'options' => null],
            ['field_key' => 'vendor',       'label' => 'ผู้จำหน่าย / Vendor',    'field_type' => 'text',    'is_required' => false, 'sort_order' => 2, 'options' => null],
            ['field_key' => 'purpose',      'label' => 'รายละเอียด / เหตุผล',    'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 3, 'options' => null],
            ['field_key' => 'total_amount', 'label' => 'ยอดรวม (บาท)',           'field_type' => 'number',  'is_required' => true,  'sort_order' => 4, 'options' => null],
        ];
        foreach ($purchaseFields as $f) {
            DocumentFormField::create(['form_id' => $purchaseForm->id] + $f);
        }
        // restrict to Accounting org unit
        $purchaseForm->orgUnits()->sync([$acctOrg->id]);

        // ---- 8. DocumentFormWorkflowPolicy --------------------------------

        // 8a. Leave policy (HR org-unit-specific, fixed workflow)
        $leavePolicy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $leaveForm->id, 'org_unit_id' => $hrOrg->id],
            [
                'use_amount_condition' => false,
                'amount_field_key' => null,
                'workflow_id' => $wfLeave->id,
            ]
        );

        // 8b. Purchase policy (ACCT org-unit-specific, amount-based)
        $purchasePolicy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $purchaseForm->id, 'org_unit_id' => $acctOrg->id],
            [
                'use_amount_condition' => true,
                'amount_field_key' => 'total_amount',
                'workflow_id' => null,
            ]
        );
        $purchasePolicy->ranges()->delete();
        DocumentFormWorkflowRange::create([
            'policy_id' => $purchasePolicy->id,
            'min_amount' => 0,
            'max_amount' => 50000.00,
            'workflow_id' => $wfPurchaseSmall->id,
            'sort_order' => 1,
        ]);
        DocumentFormWorkflowRange::create([
            'policy_id' => $purchasePolicy->id,
            'min_amount' => 50000.01,
            'max_amount' => null,
            'workflow_id' => $wfPurchaseLarge->id,
            'sort_order' => 2,
        ]);

        // ---- 9. Running Numbers -------------------------------------------
        RunningNumberConfig::firstOrCreate(
            ['document_type' => 'leave_request'],
            ['prefix' => 'LV-', 'digit_count' => 4, 'reset_mode' => 'year', 'include_year' => true, 'include_month' => false, 'last_number' => 0, 'is_active' => true]
        );
        RunningNumberConfig::firstOrCreate(
            ['document_type' => 'purchase_request'],
            ['prefix' => 'PO-', 'digit_count' => 4, 'reset_mode' => 'year', 'include_year' => true, 'include_month' => false, 'last_number' => 0, 'is_active' => true]
        );

        // ---- 10. Global setting -------------------------------------------
        Setting::set('approval.allow_requester_override', true);

        $this->command->info('UatMultiApprovalSeeder done.');
        $this->command->info('New users (password: Test@1234):');
        $this->command->table(
            ['email', 'name', 'role', 'dept'],
            [
                ['hr.staff@abc.co.th',   'สมหมาย ใจดี',      'employee',   'HR'],
                ['hr.sup1@abc.co.th',    'มานะ รักงาน',      'approver', 'HR'],
                ['hr.sup2@abc.co.th',    'มานิ ขยัน',        'approver', 'HR'],
                ['hr.sup3@abc.co.th',    'มาลี สุขใจ',       'approver', 'HR'],
                ['acct.staff@abc.co.th', 'บัญชา ตรงไป',      'employee',   'ACCT'],
                ['acct.mgr1@abc.co.th',  'ชาญ บัญชีดี',      'approver', 'ACCT'],
                ['acct.mgr2@abc.co.th',  'ชาลี เก่งเลข',     'approver', 'ACCT'],
                ['acct.mgr3@abc.co.th',  'ชัย รักเลข',       'approver', 'ACCT'],
                ['it.head2@abc.co.th',   'สมเกียรติ ดีงาม',  'approver', 'IT'],
                ['it.head3@abc.co.th',   'สมภพ สุดยอด',      'approver', 'IT'],
            ]
        );
    }

    private function ensureApprovalPermission(): void
    {
        if (! Permission::query()->where('name', 'approval.approve')->where('guard_name', 'web')->exists()) {
            $p = new Permission;
            $p->name = 'approval.approve';
            $p->guard_name = 'web';
            $p->module = 'approval';
            $p->action = 'approve';
            $p->save();
        }
    }

    private function makeUser(
        string $email,
        string $firstName,
        string $lastName,
        int $orgUnitId,
        ?int $positionId,
        Role $role,
        string $hashedPassword
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $hashedPassword,
                'org_unit_id' => $orgUnitId,
                'position_id' => $positionId,
                'is_active' => true,
                'is_super_admin' => false,
            ]
        );
        $user->assignRole($role);

        return $user;
    }
}
