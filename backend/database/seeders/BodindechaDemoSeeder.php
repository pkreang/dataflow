<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Company;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\Position;
use App\Models\RunningNumberConfig;
use App\Models\User;
use App\Services\FormSchemaService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * โรงเรียนบดินทรเดชา (สิงห์ สิงหเสนี) — eForm + workflow demo
 *
 * Seeds: company, departments (4 กลุ่มบริหาร + 3 กลุ่มสาระตัวอย่าง), positions (5),
 * users (6), document types (2), workflows (2), forms (2: ใบลา + ใบขอจัดกิจกรรม),
 * with form department visibility to isolate from NTEQ data.
 *
 * Idempotent (updateOrCreate). Safe to re-run.
 *
 *   php artisan db:seed --class=BodindechaDemoSeeder
 */
class BodindechaDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Company ──────────────────────────────────────
        $school = Company::updateOrCreate(
            ['code' => 'BODIN'],
            [
                'name' => 'โรงเรียนบดินทรเดชา (สิงห์ สิงหเสนี)',
                'business_type' => 'โรงเรียนมัธยมศึกษาขนาดใหญ่พิเศษ',
                'address' => '40 ซอยรามคำแหง 43/1 แขวงพลับพลา เขตวังทองหลาง',
                'address_province' => 'กรุงเทพมหานคร',
                'address_postal_code' => '10310',
                'phone' => '02-538-3722',
                'email' => 'info@bodin.ac.th',
                'is_active' => true,
            ]
        );
        $this->command?->info('Company: บดินทรเดชา');

        // ── 2. Document Types ───────────────────────────────
        foreach ([
            ['code' => 'bd_leave_request',     'label_en' => 'Leave Request (BD)',         'label_th' => 'ใบลา (บ.ด.)',              'icon' => 'calendar-days',     'sort_order' => 30],
            ['code' => 'bd_activity_approval', 'label_en' => 'Activity Approval (BD)',     'label_th' => 'ใบขออนุมัติกิจกรรม (บ.ด.)', 'icon' => 'clipboard-document', 'sort_order' => 31],
        ] as $dt) {
            DocumentType::updateOrCreate(
                ['code' => $dt['code']],
                ['label_en' => $dt['label_en'], 'label_th' => $dt['label_th'], 'icon' => $dt['icon'], 'sort_order' => $dt['sort_order'], 'routing_mode' => 'hybrid', 'is_active' => true]
            );
        }

        // ── 3. Departments (7) ──────────────────────────────
        //    4 กลุ่มบริหาร + 3 กลุ่มสาระตัวอย่าง
        $departments = [
            ['code' => 'BD_ACAD',   'name' => 'กลุ่มบริหารวิชาการ',       'description' => 'Academic Affairs — หลักสูตร วัดผล'],
            ['code' => 'BD_BUDGET', 'name' => 'กลุ่มบริหารงบประมาณ',      'description' => 'Budget — การเงิน พัสดุ'],
            ['code' => 'BD_HR',     'name' => 'กลุ่มบริหารบุคคล',         'description' => 'HR — ลา เลื่อนขั้น วินัย'],
            ['code' => 'BD_ADMIN',  'name' => 'กลุ่มบริหารทั่วไป',        'description' => 'General Admin — อาคารสถานที่ ประชาสัมพันธ์'],
            ['code' => 'BD_THAI',   'name' => 'กลุ่มสาระภาษาไทย',        'description' => 'Thai Language Department'],
            ['code' => 'BD_MATH',   'name' => 'กลุ่มสาระคณิตศาสตร์',      'description' => 'Mathematics Department'],
            ['code' => 'BD_SCI',    'name' => 'กลุ่มสาระวิทยาศาสตร์ฯ',    'description' => 'Science & Technology Department'],
        ];

        $deptMap = [];
        foreach ($departments as $d) {
            $dept = Department::updateOrCreate(['code' => $d['code']], ['name' => $d['name'], 'description' => $d['description']]);
            $deptMap[$d['code']] = $dept;
        }
        $this->command?->info('Departments: '.count($departments));

        // ── 4. Positions (5) ────────────────────────────────
        $positions = [
            ['code' => 'BD_DIRECTOR',     'name' => 'ผู้อำนวยการโรงเรียน',    'description' => 'School Director — อนุมัติขั้นสุดท้าย'],
            ['code' => 'BD_VICE_DIR',     'name' => 'รองผู้อำนวยการ',         'description' => 'Vice Director — อนุมัติขั้นที่ 2'],
            ['code' => 'BD_DEPT_HEAD',    'name' => 'หัวหน้ากลุ่มสาระ',       'description' => 'Department Head — อนุมัติขั้นที่ 1'],
            ['code' => 'BD_TEACHER',      'name' => 'ครู',                   'description' => 'Teacher — ผู้ยื่นคำขอ'],
            ['code' => 'BD_ADMIN_STAFF',  'name' => 'เจ้าหน้าที่ธุรการ',      'description' => 'Admin Staff'],
        ];

        $posMap = [];
        foreach ($positions as $p) {
            $pos = Position::updateOrCreate(['code' => $p['code']], ['name' => $p['name'], 'description' => $p['description'], 'is_active' => true]);
            $posMap[$p['code']] = $pos;
        }
        $this->command?->info('Positions: '.count($positions));

        // ── 5. Users (6) ────────────────────────────────────
        $approverRole = Role::where('name', 'approver')->where('guard_name', 'web')->first();
        $employeeRole = Role::where('name', 'employee')->where('guard_name', 'web')->first();

        $users = [
            ['email' => 'teacher.thai@bodin.test',  'first_name' => 'สุดา',     'last_name' => 'รักภาษา',   'dept' => 'BD_THAI',   'pos' => 'BD_TEACHER',    'role' => $employeeRole],
            ['email' => 'teacher.math@bodin.test',  'first_name' => 'ประยุทธ์',  'last_name' => 'คำนวณเก่ง', 'dept' => 'BD_MATH',   'pos' => 'BD_TEACHER',    'role' => $employeeRole],
            ['email' => 'head.thai@bodin.test',     'first_name' => 'วิภา',     'last_name' => 'หัวหน้าสาระ', 'dept' => 'BD_THAI',   'pos' => 'BD_DEPT_HEAD',  'role' => $approverRole],
            ['email' => 'vice.hr@bodin.test',       'first_name' => 'อนันต์',   'last_name' => 'รองบุคคล',  'dept' => 'BD_HR',     'pos' => 'BD_VICE_DIR',   'role' => $approverRole],
            ['email' => 'vice.acad@bodin.test',     'first_name' => 'พรรณี',    'last_name' => 'รองวิชาการ', 'dept' => 'BD_ACAD',   'pos' => 'BD_VICE_DIR',   'role' => $approverRole],
            ['email' => 'director@bodin.test',      'first_name' => 'กัญญาพัชญ์', 'last_name' => 'กานต์ภูวนันต์', 'dept' => 'BD_ADMIN', 'pos' => 'BD_DIRECTOR', 'role' => $approverRole],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'first_name' => $u['first_name'],
                    'last_name' => $u['last_name'],
                    'password' => 'Bodin1234!',
                    'department_id' => $deptMap[$u['dept']]->id,
                    'position_id' => $posMap[$u['pos']]->id,
                    'company_id' => $school->id,
                    'is_active' => true,
                    'is_super_admin' => false,
                ]
            );
            if ($u['role'] && ! $user->hasRole($u['role']->name)) {
                $user->syncRoles([$u['role']->name]);
            }
        }
        $this->command?->info('Users: '.count($users));

        // ── 6. Workflows ────────────────────────────────────

        // 6a. ใบลา: ครู → หัวหน้ากลุ่มสาระ → รอง ผอ.(บุคคล) → ผอ.
        $wfLeave = $this->syncWorkflow('บ.ด. — อนุมัติใบลา', 'bd_leave_request', 'ครู → หัวหน้ากลุ่มสาระ → รอง ผอ. → ผอ.', [
            ['step_no' => 1, 'name' => 'หัวหน้ากลุ่มสาระอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $posMap['BD_DEPT_HEAD']->id],
            ['step_no' => 2, 'name' => 'รอง ผอ.อนุมัติ',         'approver_type' => 'position', 'approver_ref' => $posMap['BD_VICE_DIR']->id],
            ['step_no' => 3, 'name' => 'ผอ.อนุมัติ',             'approver_type' => 'position', 'approver_ref' => $posMap['BD_DIRECTOR']->id],
        ]);

        // 6b. ขอจัดกิจกรรม: ครู → รอง ผอ.(วิชาการ) → ผอ.
        $wfActivity = $this->syncWorkflow('บ.ด. — อนุมัติกิจกรรม', 'bd_activity_approval', 'ครู → รอง ผอ.(วิชาการ) → ผอ.', [
            ['step_no' => 1, 'name' => 'รอง ผอ.วิชาการอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $posMap['BD_VICE_DIR']->id],
            ['step_no' => 2, 'name' => 'ผอ.อนุมัติ',           'approver_type' => 'position', 'approver_ref' => $posMap['BD_DIRECTOR']->id],
        ]);

        $this->command?->info('Workflows: leave (3 steps) + activity (2 steps)');

        // ── 7. Workflow Bindings ─────────────────────────────
        $bdDepts = ['BD_THAI', 'BD_MATH', 'BD_SCI'];
        foreach ($bdDepts as $code) {
            DepartmentWorkflowBinding::updateOrCreate(
                ['department_id' => $deptMap[$code]->id, 'document_type' => 'bd_leave_request'],
                ['workflow_id' => $wfLeave->id]
            );
            DepartmentWorkflowBinding::updateOrCreate(
                ['department_id' => $deptMap[$code]->id, 'document_type' => 'bd_activity_approval'],
                ['workflow_id' => $wfActivity->id]
            );
        }
        $this->command?->info('Bindings: 3 กลุ่มสาระ → leave + activity workflows');

        // ── 7.5 Lookup lists (DB-driven) ────────────
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

        $targetGroupList = LookupList::updateOrCreate(
            ['key' => 'activity_target_group'],
            ['label_en' => 'Activity Target Group', 'label_th' => 'กลุ่มเป้าหมายกิจกรรม', 'is_system' => false, 'is_active' => true, 'sort_order' => 21]
        );
        foreach ([
            ['value' => 'middle_school', 'label_en' => 'Middle School Students', 'label_th' => 'นักเรียน ม.ต้น',    'sort_order' => 1],
            ['value' => 'high_school',   'label_en' => 'High School Students',   'label_th' => 'นักเรียน ม.ปลาย',   'sort_order' => 2],
            ['value' => 'all_students',  'label_en' => 'All Students',           'label_th' => 'นักเรียนทุกระดับ', 'sort_order' => 3],
            ['value' => 'faculty_staff', 'label_en' => 'Faculty and Staff',      'label_th' => 'ครูและบุคลากร',    'sort_order' => 4],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $targetGroupList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $budgetSourceList = LookupList::updateOrCreate(
            ['key' => 'activity_budget_source'],
            ['label_en' => 'Activity Budget Source', 'label_th' => 'แหล่งงบประมาณกิจกรรม', 'description' => 'ผูก visibility rule ของช่อง "ประมาณการงบประมาณ" และ "รายการค่าใช้จ่าย"', 'is_system' => true, 'is_active' => true, 'sort_order' => 22]
        );
        foreach ([
            ['value' => 'school_budget', 'label_en' => 'School Budget', 'label_th' => 'งบประมาณโรงเรียน', 'sort_order' => 1],
            ['value' => 'donation',      'label_en' => 'Donation',      'label_th' => 'เงินบริจาค',        'sort_order' => 2],
            ['value' => 'obec_budget',   'label_en' => 'OBEC Budget',   'label_th' => 'งบ สพฐ.',           'sort_order' => 3],
            ['value' => 'no_budget',     'label_en' => 'No Budget',     'label_th' => 'ไม่ใช้งบประมาณ',    'sort_order' => 4],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $budgetSourceList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        // ── 8. Form: ใบลา ────────────────────────────────────
        $bdDeptIds = collect($bdDepts)->map(fn ($c) => $deptMap[$c]->id)->all();

        $leaveForm = $this->syncForm(
            'bd_leave',
            'ใบลา (บดินทรเดชา)',
            'bd_leave_request',
            'ใบลาป่วย/ลากิจ/ลาพักผ่อน — workflow 3 ขั้น',
            2,
            'bd_leave',
            [
                ['field_key' => 'reference_no',    'label' => 'เลขที่เอกสาร',       'field_type' => 'auto_number', 'is_required' => false, 'sort_order' => 1],
                ['field_key' => 'leave_type',      'label' => 'ประเภทการลา',        'field_type' => 'lookup',   'is_required' => true,  'sort_order' => 2,
                    'options' => ['source' => 'leave_type']],
                ['field_key' => 'sick_certificate', 'label' => 'ใบรับรองแพทย์',      'field_type' => 'file',     'is_required' => false, 'sort_order' => 2,
                    'visibility_rules' => [['field' => 'leave_type', 'operator' => 'equals', 'value' => 'sick']]],
                ['field_key' => 'date_from',        'label' => 'ตั้งแต่วันที่',        'field_type' => 'date',     'is_required' => true,  'sort_order' => 3],
                ['field_key' => 'date_to',          'label' => 'ถึงวันที่',            'field_type' => 'date',     'is_required' => true,  'sort_order' => 4],
                ['field_key' => 'total_days',       'label' => 'รวม (วัน)',           'field_type' => 'number',   'is_required' => true,  'sort_order' => 5,
                    'validation_rules' => ['min' => 0.5, 'max' => 120]],
                ['field_key' => 'reason',           'label' => 'เหตุผลการลา',         'field_type' => 'textarea', 'is_required' => true,  'sort_order' => 6,
                    'validation_rules' => ['min_length' => 10]],
                ['field_key' => 'contact_phone',    'label' => 'เบอร์ติดต่อระหว่างลา', 'field_type' => 'phone',    'is_required' => true,  'sort_order' => 7],
                ['field_key' => 'substitute',       'label' => 'ผู้ปฏิบัติหน้าที่แทน', 'field_type' => 'text',     'is_required' => false, 'sort_order' => 8],
                ['field_key' => 'signature',        'label' => 'ลายมือชื่อ',          'field_type' => 'signature', 'is_required' => false, 'sort_order' => 9],
            ],
            $bdDeptIds
        );

        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $leaveForm->id, 'department_id' => null],
            ['use_amount_condition' => false, 'workflow_id' => $wfLeave->id]
        );

        // ── 9. Form: ใบขอจัดกิจกรรม ─────────────────────────
        $actForm = $this->syncForm(
            'bd_activity',
            'ใบขออนุมัติจัดกิจกรรม (บดินทรเดชา)',
            'bd_activity_approval',
            'ขออนุมัติจัดกิจกรรมการเรียนรู้ — workflow 2 ขั้น',
            2,
            'bd_activity',
            [
                ['field_key' => 'reference_no',     'label' => 'เลขที่เอกสาร',             'field_type' => 'auto_number', 'is_required' => false, 'sort_order' => 1],
                ['field_key' => 'activity_name',    'label' => 'ชื่อกิจกรรม',              'field_type' => 'text',     'is_required' => true,  'sort_order' => 2],
                ['field_key' => 'objective',         'label' => 'วัตถุประสงค์',             'field_type' => 'textarea', 'is_required' => true,  'sort_order' => 2,
                    'validation_rules' => ['min_length' => 20]],
                ['field_key' => 'target_group',      'label' => 'กลุ่มเป้าหมาย',            'field_type' => 'lookup',   'is_required' => true,  'sort_order' => 3,
                    'options' => ['source' => 'activity_target_group']],
                ['field_key' => 'participants',      'label' => 'จำนวนผู้เข้าร่วม (คน)',     'field_type' => 'number',   'is_required' => true,  'sort_order' => 4,
                    'validation_rules' => ['min' => 1, 'max' => 5000]],
                ['field_key' => 'section_schedule',  'label' => 'กำหนดการ',                'field_type' => 'section',  'is_required' => false, 'sort_order' => 5],
                ['field_key' => 'date_from',         'label' => 'วันที่เริ่ม',               'field_type' => 'date',     'is_required' => true,  'sort_order' => 6],
                ['field_key' => 'date_to',           'label' => 'วันที่สิ้นสุด',             'field_type' => 'date',     'is_required' => true,  'sort_order' => 7],
                ['field_key' => 'venue',             'label' => 'สถานที่จัด',              'field_type' => 'text',     'is_required' => true,  'sort_order' => 8],
                ['field_key' => 'is_external',       'label' => 'จัดนอกสถานที่',           'field_type' => 'checkbox', 'is_required' => false, 'sort_order' => 9,
                    'options' => ['ใช่']],
                ['field_key' => 'transport',         'label' => 'การเดินทาง',              'field_type' => 'text',     'is_required' => false, 'sort_order' => 10,
                    'visibility_rules' => [['field' => 'is_external', 'operator' => 'equals', 'value' => 'ใช่']]],
                ['field_key' => 'section_budget',    'label' => 'งบประมาณ',                'field_type' => 'section',  'is_required' => false, 'sort_order' => 11],
                ['field_key' => 'budget_source',     'label' => 'แหล่งงบประมาณ',           'field_type' => 'lookup',   'is_required' => true,  'sort_order' => 12,
                    'options' => ['source' => 'activity_budget_source']],
                ['field_key' => 'estimated_budget',  'label' => 'ประมาณการงบประมาณ (บาท)',  'field_type' => 'currency', 'is_required' => false, 'sort_order' => 13,
                    'visibility_rules' => [['field' => 'budget_source', 'operator' => 'not_equals', 'value' => 'no_budget']],
                    'validation_rules' => ['min' => 0, 'max' => 500000]],
                ['field_key' => 'budget_items',      'label' => 'รายการค่าใช้จ่าย',         'field_type' => 'table',    'is_required' => false, 'sort_order' => 14,
                    'options' => ['columns' => [
                        ['key' => 'item',   'label' => 'รายการ',  'type' => 'text'],
                        ['key' => 'amount', 'label' => 'จำนวนเงิน', 'type' => 'number'],
                    ]],
                    'visibility_rules' => [['field' => 'budget_source', 'operator' => 'not_equals', 'value' => 'no_budget']]],
                ['field_key' => 'requester_sign',    'label' => 'ลายมือชื่อผู้ขออนุมัติ',    'field_type' => 'signature', 'is_required' => false, 'sort_order' => 15],
            ],
            $bdDeptIds
        );

        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $actForm->id, 'department_id' => null],
            ['use_amount_condition' => false, 'workflow_id' => $wfActivity->id]
        );

        // ── 10. Running Numbers ──────────────────────────────
        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'bd_leave_request'],
            ['prefix' => 'LV', 'digit_count' => 4, 'reset_mode' => 'yearly',
                'include_year' => true, 'include_month' => false, 'is_active' => true]
        );
        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'bd_activity_approval'],
            ['prefix' => 'ACT', 'digit_count' => 4, 'reset_mode' => 'yearly',
                'include_year' => true, 'include_month' => false, 'is_active' => true]
        );
        $this->command?->info('Running Numbers: LV2026-0001 / ACT2026-0001');

        $this->command?->info('Forms: bd_leave (9 fields) + bd_activity (15 fields) — fdata tables created');
        $this->command?->info('');
        $this->command?->info('✓ บดินทรเดชา demo ready!');
        $this->command?->info('  Login accounts:');
        $this->command?->info('    teacher.thai@bodin.test  (ครูภาษาไทย — requester)');
        $this->command?->info('    teacher.math@bodin.test  (ครูคณิต — requester)');
        $this->command?->info('    head.thai@bodin.test     (หัวหน้ากลุ่มสาระ — approver step 1 ใบลา)');
        $this->command?->info('    vice.hr@bodin.test       (รอง ผอ.บุคคล — approver step 2 ใบลา)');
        $this->command?->info('    vice.acad@bodin.test     (รอง ผอ.วิชาการ — approver step 1 กิจกรรม)');
        $this->command?->info('    director@bodin.test      (ผอ. — approver final)');
        $this->command?->info('  Password: Bodin1234!');

        // School-focused dashboard (school_eforms data source)
        $this->call(DashboardSeeder::class);
        $this->command?->info('');
        $this->command?->info('  ฟอร์มมองเห็นเฉพาะ 3 กลุ่มสาระ (ภาษาไทย, คณิต, วิทย์)');
        $this->command?->info('  NTEQ users จะไม่เห็นฟอร์มบดินทร์ / บดินทร์ users จะไม่เห็นฟอร์ม NTEQ');
    }

    // ── Helper methods ──────────────────────────────────────

    private function syncWorkflow(string $name, string $documentType, string $description, array $stages): ApprovalWorkflow
    {
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => $name],
            ['document_type' => $documentType, 'description' => $description, 'is_active' => true]
        );
        $wf->stages()->delete();
        foreach ($stages as $s) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $wf->id,
                'step_no' => $s['step_no'],
                'name' => $s['name'],
                'approver_type' => $s['approver_type'],
                'approver_ref' => (string) $s['approver_ref'],
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }

        return $wf;
    }

    /**
     * @param  int[]  $visibleDeptIds  Department IDs that can see this form (empty = all)
     */
    private function syncForm(string $formKey, string $name, string $documentType, string $description, int $columns, string $tableName, array $fields, array $visibleDeptIds = []): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => $formKey],
            [
                'name' => $name,
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
                'layout_columns' => $columns,
                'submission_table' => $tableName,
            ]
        );

        // Pre-enable is_searchable so the list filter bar is useful right after seeding.
        $searchableDefaults = [
            'leave_type', 'date_from', 'date_to', 'target_group',
            'activity_name', 'venue', 'total_days', 'participants',
        ];
        $form->fields()->delete();
        foreach ($fields as $f) {
            $form->fields()->create([
                'field_key' => $f['field_key'],
                'label' => $f['label'],
                'field_type' => $f['field_type'],
                'is_required' => $f['is_required'] ?? false,
                'is_searchable' => in_array($f['field_key'], $searchableDefaults, true),
                'sort_order' => $f['sort_order'],
                'col_span' => 0,
                'placeholder' => $f['placeholder'] ?? null,
                'options' => $f['options'] ?? null,
                'visibility_rules' => $f['visibility_rules'] ?? null,
                'validation_rules' => $f['validation_rules'] ?? null,
            ]);
        }

        // Set department visibility
        if (! empty($visibleDeptIds)) {
            $form->departments()->sync($visibleDeptIds);
        }

        // Create dedicated table
        app(FormSchemaService::class)->createTable($form->load('fields'));

        return $form;
    }
}
