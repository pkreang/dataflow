<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentFormWorkflowRange;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\ReportDashboard;
use App\Models\ReportDashboardWidget;
use App\Models\RunningNumberConfig;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSubstitution;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Neutral-company sales demo — บริษัท เดโม จำกัด + เอกสารหลัก 6 ใบ
 * ที่ map แต่ละใบเข้ากับความสามารถ workflow ที่ต่างกัน เพื่อโชว์ความกว้าง
 * ของระบบในรอบ demo เดียว:
 *
 *   ใบลา          → สายบังคับบัญชา (direct_manager) → ผจก. + escalation + ผู้อนุมัติแทน
 *   ใบขอซื้อ (PR)  → routing ตามยอดเงิน (≤10,000 หัวหน้า / >10,000 หัวหน้า→ผจก.)
 *   ใบสั่งซื้อ (PO) → ผจก. → การเงิน (ต้องเซ็นชื่อตอนอนุมัติ) + QR บนเอกสาร
 *   เบิกค่าใช้จ่าย → 2 ใน 3 (คณะกรรมการ หัวหน้า/ผจก./ผอ.)
 *   Memo          → ผู้ยื่นเลือกผู้อนุมัติเอง (requester override)
 *   แจ้งซ่อม       → อนุมัติขั้นเดียว (fast-track)
 *
 * + Dashboard ผู้บริหาร (HomeDashboardSeeder) และยื่นแทน (on-behalf) โดย HR.
 *
 * ตั้ง ORG_VERTICAL=factory (base lang = องค์กร/สาขา) เพื่อคำกลางๆ — อย่ารัน
 * Nteq/Bodindecha seeder คู่กัน. Idempotent (updateOrCreate). ปลอดภัยถ้ารันซ้ำ.
 *
 *   php artisan db:seed --class=GenericDemoSeeder
 *
 * บัญชีทดสอบ (รหัส: password):
 *   staff@demo.test     — ผู้ยื่น (employee)
 *   head@demo.test      — หัวหน้าแผนก (approver)
 *   manager@demo.test   — ผู้จัดการ (approver)
 *   director@demo.test  — ผู้อำนวยการ (approver)
 *   finance@demo.test   — ฝ่ายการเงิน (approver)
 *   hr@demo.test        — ฝ่ายบุคคล (approver + ยื่นแทนคนอื่นได้)
 *   admin@example.com   — super-admin (จาก base seed)
 */
class GenericDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ฟอร์ม 6 ใบ — reuse template กลางๆ ที่มีอยู่แล้ว
        $this->call([
            LeaveRequestTemplateSeeder::class,   // ใบลา
            MemoTemplateSeeder::class,           // Memo
            PurchaseTemplateSeeder::class,       // ใบขอซื้อ (PR) + ใบสั่งซื้อ (PO)
            ExpenseClaimTemplateSeeder::class,   // เบิกค่าใช้จ่าย
            FactoryCmmsTemplateSeeder::class,    // แจ้งซ่อม (repair_request)
        ]);

        // 2. บริษัท + สาขา (ชื่อกลางๆ)
        $company = Company::updateOrCreate(
            ['code' => 'DEMO'],
            ['name' => 'บริษัท เดโม จำกัด', 'tax_id' => '0000000000000', 'is_active' => true]
        );
        $branch = Branch::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-HQ'],
            ['name' => 'สำนักงานใหญ่', 'is_active' => true]
        );

        // 3. ตำแหน่ง
        $posStaff = Position::updateOrCreate(['code' => 'DEMO_STAFF'], ['name' => 'พนักงาน', 'is_active' => true]);
        $posHead = Position::updateOrCreate(['code' => 'DEMO_HEAD'], ['name' => 'หัวหน้าแผนก', 'is_active' => true]);
        $posMgr = Position::updateOrCreate(['code' => 'DEMO_MGR'], ['name' => 'ผู้จัดการ', 'is_active' => true]);
        $posDir = Position::updateOrCreate(['code' => 'DEMO_DIRECTOR'], ['name' => 'ผู้อำนวยการ', 'is_active' => true]);
        $posFin = Position::updateOrCreate(['code' => 'DEMO_FINANCE'], ['name' => 'เจ้าหน้าที่การเงิน', 'is_active' => true]);
        $posHr = Position::updateOrCreate(['code' => 'DEMO_HR'], ['name' => 'เจ้าหน้าที่บุคคล', 'is_active' => true]);

        // 4. ผู้ใช้
        $employeeRole = Role::where('name', 'employee')->first();
        $approverRole = Role::where('name', 'approver')->first();

        $staff = $this->upsertUser('staff@demo.test', 'สมชาย', 'พนักงาน', $posStaff, $company, $branch);
        $head = $this->upsertUser('head@demo.test', 'สมหญิง', 'หัวหน้า', $posHead, $company, $branch);
        $manager = $this->upsertUser('manager@demo.test', 'สมศักดิ์', 'ผู้จัดการ', $posMgr, $company, $branch);
        $director = $this->upsertUser('director@demo.test', 'สมศรี', 'ผู้อำนวยการ', $posDir, $company, $branch);
        $finance = $this->upsertUser('finance@demo.test', 'มาลี', 'การเงิน', $posFin, $company, $branch);
        $hr = $this->upsertUser('hr@demo.test', 'สมคิด', 'บุคคล', $posHr, $company, $branch);

        if ($employeeRole) {
            $staff->syncRoles([$employeeRole]);
        }
        if ($approverRole) {
            foreach ([$head, $manager, $director, $finance, $hr] as $u) {
                $u->syncRoles([$approverRole]);
            }
        }

        // ยื่นแทนคนอื่นได้ (on-behalf) — HR
        if (Permission::where('name', 'submission.create_for_others')->exists()) {
            $hr->givePermissionTo('submission.create_for_others');
        }

        // 5. หน่วยงาน (org_units) + ผูกผู้ใช้ + สายบังคับบัญชา
        $root = OrgUnit::updateOrCreate(
            ['name' => 'บริษัท เดโม จำกัด'],
            ['type' => 'company', 'parent_id' => null, 'head_user_id' => $director->id, 'sort_order' => 1, 'is_active' => true]
        );
        $ouOps = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายปฏิบัติการ'],
            ['type' => 'department', 'parent_id' => $root->id, 'head_user_id' => $head->id, 'sort_order' => 1, 'is_active' => true]
        );
        $ouFin = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายบัญชีและการเงิน'],
            ['type' => 'department', 'parent_id' => $root->id, 'head_user_id' => $manager->id, 'sort_order' => 2, 'is_active' => true]
        );
        $ouHr = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายทรัพยากรบุคคล'],
            ['type' => 'department', 'parent_id' => $root->id, 'head_user_id' => $hr->id, 'sort_order' => 3, 'is_active' => true]
        );

        $staff->update(['org_unit_id' => $ouOps->id, 'manager_id' => $head->id]);
        $head->update(['org_unit_id' => $ouOps->id, 'manager_id' => $manager->id]);
        $manager->update(['org_unit_id' => $root->id, 'manager_id' => $director->id]);
        $director->update(['org_unit_id' => $root->id]);
        $finance->update(['org_unit_id' => $ouFin->id, 'manager_id' => $manager->id]);
        $hr->update(['org_unit_id' => $ouHr->id, 'manager_id' => $manager->id]);

        // 6. เปิด requester override ระดับระบบ + ผู้อนุมัติแทน (head ลา → hr อนุมัติแทน)
        Setting::set('approval.allow_requester_override', true);
        UserSubstitution::updateOrCreate(
            ['from_user_id' => $head->id, 'to_user_id' => $hr->id],
            ['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => null, 'reason' => 'เดโม: หัวหน้าลา มอบ HR อนุมัติแทน']
        );

        // 7. Workflow ต่อเอกสาร (หัวใจ demo)
        $this->seedLeaveWorkflow($manager);
        $this->seedPurchaseAmountRouting($head, $manager);
        $this->seedPoSignatureWorkflow($manager, $finance);
        $this->seedExpenseQuorum($head, $manager, $director);
        $this->seedMemoOverride($head);
        $this->normalizeRepairWorkflow();
        $this->seedRepairForm();

        // 8. Dashboard — metric ส่วนตัว (HomeDashboardSeeder) + ข้อมูลตัวอย่าง + dashboard กราฟผู้บริหาร
        $this->call(HomeDashboardSeeder::class);
        $this->seedDashboardDemoData();
        $this->seedExecutiveDashboard();

        $this->printSummary();
    }

    private function upsertUser(string $email, string $first, string $last, Position $pos, Company $company, Branch $branch): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name' => $last,
                'password' => 'password',
                'password_changed_at' => now(),
                'password_must_change' => false,
                'position_id' => $pos->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]
        );
    }

    /**
     * ใบลา → ขั้น1 หัวหน้าโดยตรง (direct_manager จาก users.manager_id) + escalation 2 วัน
     *        → ขั้น2 ผู้จัดการ. โชว์ routing ตามผังองค์กร + escalation + ผู้อนุมัติแทน.
     */
    private function seedLeaveWorkflow(User $manager): void
    {
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบลา (สายบังคับบัญชา)'],
            ['document_type' => 'leave_request', 'description' => 'หัวหน้าโดยตรง → ผู้จัดการ (มี escalation + ผู้อนุมัติแทน)', 'is_active' => true]
        );
        $wf->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 1, 'name' => 'หัวหน้าโดยตรง',
            'approver_type' => 'direct_manager', 'approver_ref' => '',
            'min_approvals' => 1, 'escalation_after_days' => 2, 'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 2, 'name' => 'ผู้จัดการ',
            'approver_type' => 'user', 'approver_ref' => (string) $manager->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        $this->bindPolicy('leave_request_default', $wf);
    }

    /**
     * ใบขอซื้อ → routing ตามยอดเงิน: เพิ่มฟิลด์ grand_total (currency) แล้วตั้ง
     * amount policy อ่านยอดจากฟิลด์นั้น. ≤10,000 หัวหน้าคนเดียว / >10,000 หัวหน้า→ผจก.
     */
    private function seedPurchaseAmountRouting(User $head, User $manager): void
    {
        $form = DocumentForm::where('form_key', 'purchase_request_default')->first();
        if (! $form) {
            return;
        }

        // เพิ่มฟิลด์ยอดรวม (currency) ก่อนช่องลายเซ็น
        DocumentFormField::where('form_id', $form->id)->where('field_key', 'notes')->update(['sort_order' => 6]);
        DocumentFormField::where('form_id', $form->id)->where('field_key', 'signature')->update(['sort_order' => 7]);
        DocumentFormField::updateOrCreate(
            ['form_id' => $form->id, 'field_key' => 'grand_total'],
            [
                'label' => 'ยอดรวม (บาท)', 'label_en' => 'Grand Total (THB)', 'label_th' => 'ยอดรวม (บาท)',
                'field_type' => 'currency', 'is_required' => true, 'is_searchable' => true,
                'sort_order' => 5, 'col_span' => 2,
            ]
        );

        $small = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบขอซื้อ — ยอดน้อย (≤10,000)'],
            ['document_type' => 'purchase_request', 'description' => 'หัวหน้าแผนกอนุมัติคนเดียว', 'is_active' => true]
        );
        $this->setStages($small, [
            ['step_no' => 1, 'name' => 'หัวหน้าแผนก', 'approver_type' => 'user', 'approver_ref' => (string) $head->id],
        ]);

        $large = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบขอซื้อ — ยอดสูง (>10,000)'],
            ['document_type' => 'purchase_request', 'description' => 'หัวหน้าแผนก → ผู้จัดการ', 'is_active' => true]
        );
        $this->setStages($large, [
            ['step_no' => 1, 'name' => 'หัวหน้าแผนก', 'approver_type' => 'user', 'approver_ref' => (string) $head->id],
            ['step_no' => 2, 'name' => 'ผู้จัดการ', 'approver_type' => 'user', 'approver_ref' => (string) $manager->id],
        ]);

        // policy: อ่านยอดจากฟิลด์ grand_total + ranges
        $policy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            ['use_amount_condition' => true, 'amount_field_key' => 'grand_total', 'workflow_id' => $small->id]
        );
        $policy->ranges()->delete();
        DocumentFormWorkflowRange::create(['policy_id' => $policy->id, 'min_amount' => 0, 'max_amount' => 10000, 'workflow_id' => $small->id, 'sort_order' => 1]);
        DocumentFormWorkflowRange::create(['policy_id' => $policy->id, 'min_amount' => 10000.01, 'max_amount' => null, 'workflow_id' => $large->id, 'sort_order' => 2]);
    }

    /**
     * ใบสั่งซื้อ → ผจก. → การเงิน (ขั้นการเงินต้องเซ็นชื่อตอนอนุมัติ) + ฟิลด์ QR บนเอกสาร.
     */
    private function seedPoSignatureWorkflow(User $manager, User $finance): void
    {
        $form = DocumentForm::where('form_key', 'purchase_order_default')->first();
        if (! $form) {
            return;
        }

        // ฟิลด์ QR สำหรับสแกนตรวจสอบเอกสารจริง (display-only, ไม่มี payload column)
        $maxSort = (int) DocumentFormField::where('form_id', $form->id)->max('sort_order');
        DocumentFormField::updateOrCreate(
            ['form_id' => $form->id, 'field_key' => 'verify_qr'],
            [
                'label' => 'QR ตรวจสอบเอกสาร', 'label_en' => 'Verification QR', 'label_th' => 'QR ตรวจสอบเอกสาร',
                'field_type' => 'qr_code', 'is_required' => false,
                'sort_order' => $maxSort + 1, 'col_span' => 2,
                'options' => ['template' => "{ref_no}\n{url}", 'size' => 128, 'label_position' => 'below'],
            ]
        );

        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบสั่งซื้อ (ผจก. → การเงิน)'],
            ['document_type' => 'purchase_order', 'description' => 'ผู้จัดการ → การเงิน (เซ็นชื่อ)', 'is_active' => true]
        );
        $wf->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 1, 'name' => 'ผู้จัดการ',
            'approver_type' => 'user', 'approver_ref' => (string) $manager->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 2, 'name' => 'การเงิน (เซ็นชื่อ)',
            'approver_type' => 'user', 'approver_ref' => (string) $finance->id,
            'min_approvals' => 1, 'require_signature' => true, 'is_active' => true,
        ]);
        $this->bindPolicy('purchase_order_default', $wf);
    }

    /**
     * เบิกค่าใช้จ่าย → 2 ใน 3: คณะกรรมการ หัวหน้า/ผจก./ผอ. ต้องอนุมัติ 2 คน.
     * base source = head + approver_rules [manager, director], min_approvals=2.
     */
    private function seedExpenseQuorum(User $head, User $manager, User $director): void
    {
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบเบิก (คณะกรรมการ 2 ใน 3)'],
            ['document_type' => 'expense_claim', 'description' => 'ต้องได้ 2 เสียงจาก หัวหน้า/ผจก./ผอ.', 'is_active' => true]
        );
        $wf->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 1, 'name' => 'คณะกรรมการ (2 ใน 3)',
            'approver_type' => 'user', 'approver_ref' => (string) $head->id,
            'approver_rules' => [
                ['type' => 'user', 'ref' => (string) $manager->id, 'min_count' => 1],
                ['type' => 'user', 'ref' => (string) $director->id, 'min_count' => 1],
            ],
            'min_approvals' => 2, 'is_active' => true,
        ]);
        $this->bindPolicy('expense_claim_default', $wf);
    }

    /**
     * Memo → ผู้ยื่นเลือกผู้อนุมัติเอง (allow_requester_override); ค่าเริ่มต้น = หัวหน้า.
     */
    private function seedMemoOverride(User $head): void
    {
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติ Memo (เลือกผู้อนุมัติเอง)'],
            ['document_type' => 'memo', 'description' => 'ผู้ยื่นเลือกผู้อนุมัติเองได้', 'is_active' => true]
        );
        $wf->stages()->delete();
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id, 'step_no' => 1, 'name' => 'ผู้อนุมัติที่ผู้ยื่นเลือก',
            'approver_type' => 'user', 'approver_ref' => (string) $head->id,
            'allow_requester_override' => true, 'min_approvals' => 1, 'is_active' => true,
        ]);
        $this->bindPolicy('memo_default', $wf);
    }

    /**
     * แจ้งซ่อม → workflow ที่ form ผูกอยู่จริงเป็น role-based ขั้นเดียว + ชื่อสะอาด.
     * หา workflow ผ่าน policy กันสร้างซ้ำตอนรันซ้ำ + ลบ orphan รอบก่อน.
     */
    private function normalizeRepairWorkflow(): void
    {
        $form = DocumentForm::where('form_key', 'repair_request_default')->first();
        if (! $form) {
            return;
        }

        $policy = DocumentFormWorkflowPolicy::where('form_id', $form->id)->whereNull('position_id')->first();
        $workflow = $policy
            ? ApprovalWorkflow::find($policy->workflow_id)
            : ApprovalWorkflow::where('document_type', 'repair_request')->first();
        if (! $workflow) {
            return;
        }

        $workflow->update(['name' => 'อนุมัติแจ้งซ่อม', 'is_active' => true]);
        $this->setStages($workflow, [
            ['step_no' => 1, 'name' => 'ผู้อนุมัติแจ้งซ่อม', 'approver_type' => 'role', 'approver_ref' => 'approver'],
        ]);

        ApprovalWorkflow::where('document_type', 'repair_request')
            ->where('id', '!=', $workflow->id)
            ->get()
            ->each(function (ApprovalWorkflow $stray): void {
                $stray->stages()->delete();
                $stray->delete();
            });
    }

    /**
     * แจ้งซ่อม → แทนชุดฟิลด์ 3 ช่องของ template ด้วยฟอร์มสมจริง (ผสม สำนักงาน+เครื่องจักร)
     * 13 ฟิลด์ 2 คอลัมน์ + ฟิลด์เงื่อนไข "เหตุผลความเร่งด่วน" (โผล่+บังคับเมื่อ ด่วนมาก).
     * ไม่แตะ workflow/policy (จัดการที่ normalizeRepairWorkflow).
     */
    private function seedRepairForm(): void
    {
        $form = DocumentForm::where('form_key', 'repair_request_default')->first();
        if (! $form) {
            return;
        }

        $form->update(['name' => 'ใบแจ้งซ่อม', 'description' => 'แจ้งซ่อม/ขอบริการงานช่าง — อาคาร/ระบบ/เครื่องจักร', 'layout_columns' => 2]);

        // เลขที่เอกสารอัตโนมัติ RP-{ปี}-0001 (FactoryCmms ไม่ได้สร้างให้)
        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'repair_request'],
            ['prefix' => 'RP', 'digit_count' => 4, 'reset_mode' => 'yearly', 'include_year' => true, 'include_month' => false, 'is_active' => true]
        );

        DocumentFormField::where('form_id', $form->id)->delete();

        $fields = [
            ['field_key' => 'reference_no', 'label' => 'เลขที่ใบแจ้งซ่อม', 'field_type' => 'auto_number', 'is_required' => false, 'is_readonly' => true, 'sort_order' => 1, 'col_span' => 2],
            ['field_key' => 'report_date', 'label' => 'วันที่แจ้ง', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 2, 'col_span' => 1],
            ['field_key' => 'department', 'label' => 'หน่วยงาน/แผนกผู้แจ้ง', 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 3, 'col_span' => 1],
            ['field_key' => 'repair_category', 'label' => 'ประเภทงานซ่อม', 'field_type' => 'select', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 4, 'col_span' => 1,
                'options' => ['ไฟฟ้า', 'ประปา/สุขาภิบาล', 'เครื่องปรับอากาศ', 'เครื่องจักร/อุปกรณ์', 'อาคาร/สถานที่', 'คอมพิวเตอร์/IT', 'ยานพาหนะ', 'อื่นๆ']],
            ['field_key' => 'urgency', 'label' => 'ระดับความเร่งด่วน', 'field_type' => 'radio', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 5, 'col_span' => 1,
                'options' => ['ปกติ', 'ด่วน', 'ด่วนมาก']],
            ['field_key' => 'location', 'label' => 'สถานที่ / จุดที่ชำรุด', 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 6, 'col_span' => 2],
            ['field_key' => 'asset_code', 'label' => 'รหัส/ชื่อเครื่องจักร-ทรัพย์สิน', 'field_type' => 'text', 'is_required' => false, 'is_searchable' => true, 'sort_order' => 7, 'col_span' => 1, 'placeholder' => 'เช่น AC-204, เครื่องอัด #3'],
            ['field_key' => 'downtime_hours', 'label' => 'ใช้งานไม่ได้มาแล้ว (ชม.)', 'field_type' => 'number', 'is_required' => false, 'sort_order' => 8, 'col_span' => 1, 'placeholder' => '0'],
            ['field_key' => 'title', 'label' => 'หัวข้อ/อาการเสียโดยย่อ', 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'sort_order' => 9, 'col_span' => 2],
            ['field_key' => 'detail', 'label' => 'รายละเอียดอาการ/ปัญหา', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 10, 'col_span' => 2],
            ['field_key' => 'urgency_reason', 'label' => 'เหตุผลความเร่งด่วน', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 11, 'col_span' => 2,
                'visibility_rules' => [['field' => 'urgency', 'operator' => 'equals', 'value' => 'ด่วนมาก']],
                'required_rules' => [['conditions' => [['field' => 'urgency', 'operator' => 'equals', 'value' => 'ด่วนมาก']]]]],
            ['field_key' => 'photo', 'label' => 'รูปภาพประกอบ', 'field_type' => 'image', 'is_required' => false, 'sort_order' => 12, 'col_span' => 2],
            ['field_key' => 'signature', 'label' => 'ลายเซ็นผู้แจ้ง', 'field_type' => 'signature', 'is_required' => true, 'sort_order' => 13, 'col_span' => 2],
        ];

        foreach ($fields as $data) {
            DocumentFormField::create(array_merge(['form_id' => $form->id], $data));
        }
    }

    /**
     * @param  array<int, array{step_no:int,name:string,approver_type:string,approver_ref:string}>  $stages
     */
    private function setStages(ApprovalWorkflow $workflow, array $stages): void
    {
        $workflow->stages()->delete();
        foreach ($stages as $s) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $workflow->id,
                'step_no' => $s['step_no'],
                'name' => $s['name'],
                'approver_type' => $s['approver_type'],
                'approver_ref' => (string) $s['approver_ref'],
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }
    }

    private function bindPolicy(string $formKey, ApprovalWorkflow $workflow): void
    {
        $form = DocumentForm::where('form_key', $formKey)->first();
        if (! $form) {
            return;
        }
        $policy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            ['workflow_id' => $workflow->id, 'use_amount_condition' => false, 'amount_field_key' => null]
        );
        $policy->ranges()->delete();
    }

    /**
     * ข้อมูลตัวอย่างให้กราฟ dashboard มีอะไรแสดง (idempotent — ข้ามถ้ามี submission แล้ว).
     * back-date created_at กระจาย ~6 เดือน เพื่อให้กราฟแนวโน้มรายเดือนมีหลายจุด.
     * แจ้งซ่อมตัวอย่างใช้สถานะ terminal (approved/rejected/cancelled) เท่านั้น เพื่อไม่รก
     * inbox รออนุมัติ — ใบ pending จริงมาจากการ submit สดตอน demo.
     */
    private function seedDashboardDemoData(): void
    {
        if (DocumentFormSubmission::query()->exists()) {
            return;
        }

        $formIds = DocumentForm::whereIn('form_key', [
            'leave_request_default', 'memo_default', 'purchase_request_default',
            'purchase_order_default', 'expense_claim_default', 'repair_request_default',
        ])->pluck('id')->all();
        $orgUnitIds = OrgUnit::where('type', 'department')->pluck('id')->all();
        $userIds = User::whereIn('email', [
            'staff@demo.test', 'head@demo.test', 'manager@demo.test', 'finance@demo.test', 'hr@demo.test',
        ])->pluck('id')->all();

        if (empty($formIds) || empty($orgUnitIds) || empty($userIds)) {
            return;
        }

        // ~36 form submissions: กระจายฟอร์ม/สถานะ/แผนก/เดือน
        for ($i = 0; $i < 36; $i++) {
            $when = now()->subDays(random_int(0, 175))->subHours(random_int(0, 23));
            $sub = new DocumentFormSubmission([
                'form_id' => $formIds[array_rand($formIds)],
                'user_id' => $userIds[array_rand($userIds)],
                'org_unit_id' => $orgUnitIds[array_rand($orgUnitIds)],
                'status' => $i % 6 === 0 ? 'draft' : 'submitted',
                'reference_no' => sprintf('DOC-%s-%04d', $when->format('ym'), $i + 1),
                'payload' => ['demo' => true],
            ]);
            $sub->created_at = $when;
            $sub->updated_at = $when;
            $sub->save();
        }

        // ~32 repair approval instances: terminal statuses across months
        $repairWfId = ApprovalWorkflow::where('document_type', 'repair_request')->value('id');
        if ($repairWfId) {
            $statuses = array_merge(
                array_fill(0, 18, 'approved'),
                array_fill(0, 9, 'rejected'),
                array_fill(0, 5, 'cancelled'),
            );
            foreach ($statuses as $i => $status) {
                $when = now()->subDays(random_int(0, 175))->subHours(random_int(0, 23));
                $inst = new ApprovalInstance([
                    'workflow_id' => $repairWfId,
                    'org_unit_id' => $orgUnitIds[array_rand($orgUnitIds)],
                    'requester_user_id' => $userIds[array_rand($userIds)],
                    'document_type' => 'repair_request',
                    'reference_no' => sprintf('RPD-%s-%04d', $when->format('ym'), $i + 1),
                    'payload' => ['demo' => true],
                    'current_step_no' => 1,
                    'status' => $status,
                ]);
                $inst->created_at = $when;
                $inst->updated_at = $when;
                $inst->save();
            }
        }
    }

    /**
     * Dashboard ผู้บริหาร "ภาพรวมผู้บริหาร" — โดนัท/พาย/แท่ง + เส้นแนวโน้มรายเดือน.
     * visibility=all ให้ทุกคนเห็นตอน demo. Idempotent (updateOrCreate + ลบ widget เดิม).
     */
    private function seedExecutiveDashboard(): void
    {
        $admin = User::where('is_super_admin', true)->first();

        $dashboard = ReportDashboard::updateOrCreate(
            ['name' => 'ภาพรวมผู้บริหาร'],
            [
                'description' => 'สรุปเอกสารและการแจ้งซ่อม — วงกลม/แท่ง/เส้นแนวโน้มรายเดือน',
                'layout_columns' => 3,
                'visibility' => 'all',
                'required_permission' => null,
                'is_active' => true,
                'created_by' => $admin?->id,
            ]
        );

        $dashboard->widgets()->delete();

        $widgets = [
            ['title' => 'เอกสารทั้งหมด', 'widget_type' => 'metric', 'data_source' => 'document_form_submissions', 'col_span' => 1,
                'config' => ['aggregation' => 'count', 'field' => 'id']],
            ['title' => 'รออนุมัติ (เอกสาร)', 'widget_type' => 'metric', 'data_source' => 'document_form_submissions', 'col_span' => 1,
                'config' => ['aggregation' => 'count', 'field' => 'id', 'filters' => ['status' => 'submitted']]],
            ['title' => 'แจ้งซ่อมทั้งหมด', 'widget_type' => 'metric', 'data_source' => 'repair_requests', 'col_span' => 1,
                'config' => ['aggregation' => 'count', 'field' => 'id']],
            ['title' => 'เอกสารแยกตามประเภท', 'widget_type' => 'chart', 'data_source' => 'document_form_submissions', 'col_span' => 1,
                'config' => ['chart_type' => 'donut', 'aggregation' => 'count', 'field' => 'id', 'group_by' => 'form_id']],
            ['title' => 'สถานะการแจ้งซ่อม', 'widget_type' => 'chart', 'data_source' => 'repair_requests', 'col_span' => 1,
                'config' => ['chart_type' => 'pie', 'aggregation' => 'count', 'field' => 'id', 'group_by' => 'status']],
            ['title' => 'เอกสารตามแผนก', 'widget_type' => 'chart', 'data_source' => 'document_form_submissions', 'col_span' => 1,
                'config' => ['chart_type' => 'bar', 'aggregation' => 'count', 'field' => 'id', 'group_by' => 'org_unit_id']],
            ['title' => 'แนวโน้มเอกสารรายเดือน', 'widget_type' => 'chart', 'data_source' => 'document_form_submissions', 'col_span' => 3,
                'config' => ['chart_type' => 'line', 'aggregation' => 'count', 'field' => 'id', 'group_by' => 'created_at:month']],
            ['title' => 'แนวโน้มการแจ้งซ่อมรายเดือน', 'widget_type' => 'chart', 'data_source' => 'repair_requests', 'col_span' => 3,
                'config' => ['chart_type' => 'area', 'aggregation' => 'count', 'field' => 'id', 'group_by' => 'created_at:month']],
        ];

        foreach ($widgets as $i => $w) {
            ReportDashboardWidget::create(array_merge($w, [
                'dashboard_id' => $dashboard->id,
                'sort_order' => $i + 1,
            ]));
        }
    }

    private function printSummary(): void
    {
        $this->command?->info('GenericDemoSeeder: บริษัท เดโม จำกัด + 6 eForms (workflow ขั้นสูง) พร้อมใช้.');
        $this->command?->info('  staff@demo.test    / password  (ผู้ยื่น)');
        $this->command?->info('  head@demo.test     / password  (หัวหน้าแผนก)');
        $this->command?->info('  manager@demo.test  / password  (ผู้จัดการ)');
        $this->command?->info('  director@demo.test / password  (ผู้อำนวยการ)');
        $this->command?->info('  finance@demo.test  / password  (การเงิน — เซ็นอนุมัติ PO)');
        $this->command?->info('  hr@demo.test       / password  (บุคคล — ยื่นแทน + อนุมัติแทนหัวหน้า)');
        $this->command?->info('  ใบลา=สายบังคับบัญชา · PR=ตามยอดเงิน · PO=เซ็นชื่อ+QR · เบิก=2ใน3 · Memo=เลือกผู้อนุมัติ · แจ้งซ่อม=ขั้นเดียว');
    }
}
