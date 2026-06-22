<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Company;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\OrgUnit;
use App\Models\OrgUnitWorkflowBinding;
use App\Models\Position;
use App\Models\RunningNumberConfig;
use App\Models\User;
use App\Services\FormSchemaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * NTEQ Polymer Co., Ltd. — โรงงานแปรรูปยางพารา จ.มุกดาหาร
 *
 * Seeds: company, org units (root + 7 departments), positions (8), users (8+admin),
 * document type (maintenance_request), 3-step approval workflow, maintenance eForm
 * (visibility rules + validation), maintenance lookup lists, and org unit workflow bindings.
 *
 * Idempotent (updateOrCreate). Safe to re-run.
 *
 *   php artisan db:seed --class=NteqPolymerDemoSeeder
 */
class NteqPolymerDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Company ──────────────────────────────────────
        $company = Company::updateOrCreate(
            ['code' => 'NTEQ'],
            [
                'name' => 'NTEQ Polymer Co., Ltd.',
                'tax_id' => '0495553000XXX',
                'business_type' => 'Natural Rubber Processing & Export',
                'address' => '319 หมู่ 16 ถ.ชยางกูร ต.คำป่าหลาย อ.เมือง',
                'address_province' => 'มุกดาหาร',
                'address_postal_code' => '49000',
                'phone' => '042-699-439',
                'email' => 'info@nteq-polymer.com',
                'is_active' => true,
            ]
        );
        $this->command?->info('Company: NTEQ Polymer');

        // ── 2. Document Type ────────────────────────────────
        DocumentType::updateOrCreate(
            ['code' => 'maintenance_request'],
            [
                'label_en' => 'Maintenance Request',
                'label_th' => 'ใบแจ้งซ่อม',
                'icon' => 'wrench-screwdriver',
                'sort_order' => 20,
                'routing_mode' => 'hybrid',
                'is_active' => true,
            ]
        );

        // ── 3. Org Units (root company + 7 departments) ─────
        $orgRoot = OrgUnit::firstOrCreate(
            ['name' => $company->name, 'parent_id' => null],
            ['type' => 'company', 'is_active' => true]
        );

        $departments = [
            ['code' => 'PROD',  'name' => 'ฝ่ายผลิต',                        'description' => 'สายผลิตยางแท่ง STR/MVC — กำลังผลิต 72,000 ตัน/ปี'],
            ['code' => 'MAINT', 'name' => 'ฝ่ายซ่อมบำรุง',                    'description' => 'ดูแลเครื่องจักร ระบบไฟฟ้า ระบบควบคุม'],
            ['code' => 'QC',    'name' => 'ฝ่ายควบคุมคุณภาพ',                  'description' => 'ห้องแล็บ ทดสอบ Mooney/PRI/Plasticity — ISO/IEC 17025'],
            ['code' => 'WH',    'name' => 'ฝ่ายคลังสินค้า',                    'description' => 'วัตถุดิบ (ยางก้อน/ยางแผ่น) + สินค้าสำเร็จ (STR bales)'],
            ['code' => 'PROC',  'name' => 'ฝ่ายจัดซื้อ',                      'description' => 'จัดซื้อวัตถุดิบ อะไหล่ วัสดุสิ้นเปลือง'],
            ['code' => 'EHS',   'name' => 'ฝ่ายความปลอดภัยและสิ่งแวดล้อม',    'description' => 'ISO 14001, EcoVadis, บ่อบำบัดน้ำเสีย, จป.'],
            ['code' => 'MGMT',  'name' => 'ฝ่ายบริหาร',                       'description' => 'ผู้บริหาร บัญชี HR'],
        ];

        $orgMap = [];
        foreach ($departments as $i => $d) {
            $orgMap[$d['code']] = OrgUnit::updateOrCreate(
                ['name' => $d['name'], 'parent_id' => $orgRoot->id],
                ['type' => 'department', 'is_active' => true, 'sort_order' => $i + 1]
            );
        }
        $this->command?->info('Org Units: 1 company + '.count($departments).' departments');

        // ── 4. Positions (8) ────────────────────────────────
        $positions = [
            ['code' => 'OPERATOR',    'name' => 'พนักงานปฏิบัติการ',             'description' => 'Operator — สายผลิต/คลัง/แล็บ'],
            ['code' => 'TECHNICIAN',  'name' => 'ช่างเทคนิค',                   'description' => 'Maintenance Technician'],
            ['code' => 'LAB_TECH',    'name' => 'เจ้าหน้าที่ห้องแล็บ',           'description' => 'Lab Technician — ทดสอบ Mooney/PRI'],
            ['code' => 'SHIFT_LEAD',  'name' => 'หัวหน้ากะ',                    'description' => 'Shift Leader — อนุมัติขั้นที่ 1'],
            ['code' => 'SUPERVISOR',  'name' => 'หัวหน้างาน',                   'description' => 'Supervisor'],
            ['code' => 'DEPT_MGR',    'name' => 'ผู้จัดการแผนก',                 'description' => 'Department Manager — อนุมัติขั้นที่ 2'],
            ['code' => 'PLANT_MGR',   'name' => 'ผู้จัดการโรงงาน',               'description' => 'Plant Manager — อนุมัติขั้นสุดท้าย'],
            ['code' => 'EHS_OFFICER', 'name' => 'เจ้าหน้าที่ความปลอดภัย (จป.)', 'description' => 'EHS Officer'],
        ];

        $posMap = [];
        foreach ($positions as $p) {
            $pos = Position::updateOrCreate(['code' => $p['code']], ['name' => $p['name'], 'description' => $p['description'], 'is_active' => true]);
            $posMap[$p['code']] = $pos;
        }
        $this->command?->info('Positions: '.count($positions));

        // ── 5. Users (8 + update admin) ─────────────────────
        $approverRole = Role::where('name', 'approver')->where('guard_name', 'web')->first();
        $employeeRole = Role::where('name', 'employee')->where('guard_name', 'web')->first();

        $users = [
            ['email' => 'somchai@nteq.test',    'first_name' => 'สมชาย',   'last_name' => 'เดินเครื่อง', 'dept' => 'PROD',  'pos' => 'OPERATOR',    'role' => $employeeRole],
            ['email' => 'somsri@nteq.test',     'first_name' => 'สมศรี',   'last_name' => 'คุมงาน',     'dept' => 'PROD',  'pos' => 'SHIFT_LEAD',  'role' => $approverRole],
            ['email' => 'wichai@nteq.test',     'first_name' => 'วิชัย',   'last_name' => 'ซ่อมเก่ง',   'dept' => 'MAINT', 'pos' => 'SUPERVISOR',  'role' => $approverRole],
            ['email' => 'pranee@nteq.test',     'first_name' => 'ปราณี',   'last_name' => 'จัดการดี',    'dept' => 'PROD',  'pos' => 'DEPT_MGR',    'role' => $approverRole],
            ['email' => 'manop@nteq.test',      'first_name' => 'มานพ',    'last_name' => 'สร้างสุข',    'dept' => 'MAINT', 'pos' => 'DEPT_MGR',    'role' => $approverRole],
            ['email' => 'suda@nteq.test',       'first_name' => 'สุดา',    'last_name' => 'วงศ์ประเสริฐ', 'dept' => 'QC',    'pos' => 'DEPT_MGR',    'role' => $approverRole],
            ['email' => 'somkit@nteq.test',     'first_name' => 'สมคิด',   'last_name' => 'ใหญ่มาก',   'dept' => 'MGMT',  'pos' => 'PLANT_MGR',   'role' => $approverRole],
            ['email' => 'nida@nteq.test',       'first_name' => 'นิดา',   'last_name' => 'ตรวจเข้ม',   'dept' => 'QC',    'pos' => 'LAB_TECH',    'role' => $employeeRole],
            ['email' => 'preecha@nteq.test',    'first_name' => 'ปรีชา',   'last_name' => 'ปลอดภัย',   'dept' => 'EHS',   'pos' => 'EHS_OFFICER', 'role' => $approverRole],
            ['email' => 'malee@nteq.test',      'first_name' => 'มะลิ',   'last_name' => 'จัดซื้อดี',   'dept' => 'PROC',  'pos' => 'SUPERVISOR',  'role' => $approverRole],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'first_name' => $u['first_name'],
                    'last_name' => $u['last_name'],
                    'password' => 'Nteq1234!',
                    'org_unit_id' => $orgMap[$u['dept']]->id,
                    'position_id' => $posMap[$u['pos']]->id,
                    'company_id' => $company->id,
                    'is_active' => true,
                    'is_super_admin' => false,
                ]
            );
            if ($u['role'] && ! $user->hasRole($u['role']->name)) {
                $user->syncRoles([$u['role']->name]);
            }
        }

        // Update admin user
        $admin = User::where('email', 'admin@example.com')->first();
        if ($admin) {
            $admin->update([
                'org_unit_id' => $orgMap['MGMT']->id,
                'position_id' => $posMap['PLANT_MGR']->id,
                'company_id' => $company->id,
            ]);
        }
        $this->command?->info('Users: '.count($users).' + admin updated');

        // Set each org unit's head to its most senior member (head_user_id).
        // Workflow routing is position-based, so this is for org-chart realism only.
        $seniorityRank = ['PLANT_MGR' => 6, 'DEPT_MGR' => 5, 'SUPERVISOR' => 4, 'SHIFT_LEAD' => 3, 'EHS_OFFICER' => 2, 'TECHNICIAN' => 1, 'LAB_TECH' => 1, 'OPERATOR' => 0];
        foreach ($orgMap as $orgUnit) {
            $head = $orgUnit->members()
                ->get()
                ->sortByDesc(fn ($m) => $seniorityRank[optional($m->jobPosition)->code] ?? -1)
                ->first();
            if ($head) {
                $orgUnit->update(['head_user_id' => $head->id]);
            }
        }

        // ── 9. Workflow: แจ้งซ่อม 3 ขั้น ────────────────────
        $wfMaint = ApprovalWorkflow::updateOrCreate(
            ['name' => 'NTEQ — อนุมัติแจ้งซ่อม 3 ขั้น'],
            [
                'document_type' => 'maintenance_request',
                'description' => 'พนักงาน → หัวหน้ากะ → ผจก.แผนก → ผจก.โรงงาน',
                'is_active' => true,
            ]
        );
        $wfMaint->stages()->delete();
        foreach ([
            ['step_no' => 1, 'name' => 'หัวหน้ากะอนุมัติ',       'approver_type' => 'position', 'approver_ref' => $posMap['SHIFT_LEAD']->id, 'min_approvals' => 1],
            ['step_no' => 2, 'name' => 'ผจก.แผนกอนุมัติ (2/3)', 'approver_type' => 'position', 'approver_ref' => $posMap['DEPT_MGR']->id,   'min_approvals' => 2],
            ['step_no' => 3, 'name' => 'ผจก.โรงงานอนุมัติ',      'approver_type' => 'position', 'approver_ref' => $posMap['PLANT_MGR']->id,  'min_approvals' => 1],
        ] as $stage) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $wfMaint->id,
                'step_no' => $stage['step_no'],
                'name' => $stage['name'],
                'approver_type' => $stage['approver_type'],
                'approver_ref' => (string) $stage['approver_ref'],
                'min_approvals' => $stage['min_approvals'],
                'is_active' => true,
            ]);
        }
        $this->command?->info('Workflow: 3-step maintenance approval');

        // ── 10. Org Unit ↔ Workflow Binding ──────────────────
        foreach (['PROD', 'MAINT', 'QC', 'WH'] as $deptCode) {
            OrgUnitWorkflowBinding::updateOrCreate(
                ['org_unit_id' => $orgMap[$deptCode]->id, 'document_type' => 'maintenance_request'],
                ['workflow_id' => $wfMaint->id]
            );
        }
        $this->command?->info('Workflow bindings: 4 org units → maintenance workflow');

        // ── 10.5 Lookup lists (DB-driven) ────────────
        $priorityList = LookupList::updateOrCreate(
            ['key' => 'maintenance_priority'],
            ['label_en' => 'Maintenance Priority', 'label_th' => 'ระดับความเร่งด่วน', 'description' => 'ระดับความเร่งด่วนของงานซ่อม — ผูก visibility rule ของช่อง "เหตุผลฉุกเฉิน"', 'is_system' => true, 'is_active' => true, 'sort_order' => 10]
        );
        foreach ([
            ['value' => 'normal',    'label_en' => 'Normal',    'label_th' => 'ปกติ',     'sort_order' => 1],
            ['value' => 'urgent',    'label_en' => 'Urgent',    'label_th' => 'เร่งด่วน', 'sort_order' => 2],
            ['value' => 'emergency', 'label_en' => 'Emergency', 'label_th' => 'ฉุกเฉิน',  'sort_order' => 3],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $priorityList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        // Shared severity scale
        $severityList = LookupList::updateOrCreate(
            ['key' => 'impact_severity'],
            ['label_en' => 'Impact Severity', 'label_th' => 'ระดับความรุนแรง', 'description' => 'ใช้กับ safety/quality/environmental impact fields', 'is_system' => true, 'is_active' => true, 'sort_order' => 12]
        );
        foreach ([
            ['value' => 'none',     'label_en' => 'None',     'label_th' => 'ไม่มี',   'sort_order' => 1],
            ['value' => 'low',      'label_en' => 'Low',      'label_th' => 'ต่ำ',     'sort_order' => 2],
            ['value' => 'medium',   'label_en' => 'Medium',   'label_th' => 'ปานกลาง', 'sort_order' => 3],
            ['value' => 'high',     'label_en' => 'High',     'label_th' => 'สูง',     'sort_order' => 4],
            ['value' => 'critical', 'label_en' => 'Critical', 'label_th' => 'วิกฤต',   'sort_order' => 5],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $severityList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        // Multi-select lookup lists (hazards / PPE / skills)
        $hazardList = LookupList::updateOrCreate(
            ['key' => 'maintenance_hazard'],
            ['label_en' => 'Maintenance Hazard', 'label_th' => 'อันตรายในการซ่อม', 'description' => 'ประเภทอันตรายที่พบขณะซ่อม — ผูกกับ hazards_present (multi-select)', 'is_system' => false, 'is_active' => true, 'sort_order' => 14]
        );
        foreach ([
            ['value' => 'electrical',        'label_en' => 'Electrical',            'label_th' => 'ไฟฟ้า',              'sort_order' => 1],
            ['value' => 'mechanical_moving', 'label_en' => 'Mechanical/Moving',     'label_th' => 'กลไกเคลื่อนที่',     'sort_order' => 2],
            ['value' => 'pressure',          'label_en' => 'High Pressure',         'label_th' => 'แรงดันสูง',           'sort_order' => 3],
            ['value' => 'chemical',          'label_en' => 'Chemical',              'label_th' => 'สารเคมี',             'sort_order' => 4],
            ['value' => 'heat_burn',         'label_en' => 'Heat / Burn',           'label_th' => 'ความร้อน/แผลไหม้',    'sort_order' => 5],
            ['value' => 'fire_explosion',    'label_en' => 'Fire / Explosion',      'label_th' => 'ไฟไหม้/ระเบิด',       'sort_order' => 6],
            ['value' => 'height',            'label_en' => 'Working at Height',     'label_th' => 'ทำงานที่สูง',         'sort_order' => 7],
            ['value' => 'confined_space',    'label_en' => 'Confined Space',        'label_th' => 'ที่อับอากาศ',         'sort_order' => 8],
            ['value' => 'noise',             'label_en' => 'Noise',                 'label_th' => 'เสียงดัง',            'sort_order' => 9],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $hazardList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $ppeList = LookupList::updateOrCreate(
            ['key' => 'maintenance_ppe'],
            ['label_en' => 'Required PPE', 'label_th' => 'อุปกรณ์ป้องกันส่วนบุคคล (PPE)', 'description' => 'อุปกรณ์ป้องกันที่ต้องใช้ขณะซ่อม', 'is_system' => false, 'is_active' => true, 'sort_order' => 15]
        );
        foreach ([
            ['value' => 'helmet',              'label_en' => 'Helmet',              'label_th' => 'หมวก',                 'sort_order' => 1],
            ['value' => 'safety_glasses',      'label_en' => 'Safety Glasses',      'label_th' => 'แว่นเซฟตี้',           'sort_order' => 2],
            ['value' => 'gloves',              'label_en' => 'Gloves',              'label_th' => 'ถุงมือ',               'sort_order' => 3],
            ['value' => 'hearing_protection',  'label_en' => 'Hearing Protection',  'label_th' => 'ที่อุดหู',             'sort_order' => 4],
            ['value' => 'respirator',          'label_en' => 'Respirator',          'label_th' => 'หน้ากาก respirator',   'sort_order' => 5],
            ['value' => 'safety_shoes',        'label_en' => 'Safety Shoes',        'label_th' => 'รองเท้าเซฟตี้',        'sort_order' => 6],
            ['value' => 'harness',             'label_en' => 'Safety Harness',      'label_th' => 'เข็มขัดกันตก',         'sort_order' => 7],
            ['value' => 'arc_flash_suit',      'label_en' => 'Arc-Flash Suit',      'label_th' => 'ชุดกัน arc flash',     'sort_order' => 8],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $ppeList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $skillList = LookupList::updateOrCreate(
            ['key' => 'maintenance_skill'],
            ['label_en' => 'Maintenance Skill', 'label_th' => 'ทักษะช่าง', 'description' => 'ทักษะช่างที่ต้องใช้ในงานซ่อม', 'is_system' => false, 'is_active' => true, 'sort_order' => 16]
        );
        foreach ([
            ['value' => 'electrician',     'label_en' => 'Electrician',     'label_th' => 'ช่างไฟฟ้า',       'sort_order' => 1],
            ['value' => 'mechanic',        'label_en' => 'Mechanic',        'label_th' => 'ช่างกล',           'sort_order' => 2],
            ['value' => 'welder',          'label_en' => 'Welder',          'label_th' => 'ช่างเชื่อม',       'sort_order' => 3],
            ['value' => 'instrumentation', 'label_en' => 'Instrumentation', 'label_th' => 'ช่างเครื่องมือวัด', 'sort_order' => 4],
            ['value' => 'hvac',            'label_en' => 'HVAC',            'label_th' => 'ช่าง HVAC',        'sort_order' => 5],
            ['value' => 'plumber',         'label_en' => 'Plumber',         'label_th' => 'ช่างท่อ',          'sort_order' => 6],
            ['value' => 'general',         'label_en' => 'General',         'label_th' => 'ช่างทั่วไป',       'sort_order' => 7],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $skillList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $failureModeList = LookupList::updateOrCreate(
            ['key' => 'failure_mode'],
            ['label_en' => 'Failure Mode', 'label_th' => 'ลักษณะการเสีย', 'description' => 'ลักษณะของเหตุขัดข้อง — ใช้ทำ reliability report / Pareto analysis', 'is_system' => false, 'is_active' => true, 'sort_order' => 13]
        );
        foreach ([
            ['value' => 'not_starting',        'label_en' => 'Not starting',         'label_th' => 'ไม่ติด / เปิดไม่ได้',  'sort_order' => 1],
            ['value' => 'abnormal_operation',  'label_en' => 'Abnormal operation',   'label_th' => 'ทำงานผิดปกติ',         'sort_order' => 2],
            ['value' => 'unusual_noise',       'label_en' => 'Unusual noise',        'label_th' => 'เสียงดังผิดปกติ',      'sort_order' => 3],
            ['value' => 'leakage',             'label_en' => 'Leakage',              'label_th' => 'มีการรั่วซึม',          'sort_order' => 4],
            ['value' => 'overheating',         'label_en' => 'Overheating',          'label_th' => 'ความร้อนเกิน',          'sort_order' => 5],
            ['value' => 'vibration',           'label_en' => 'Excessive vibration',  'label_th' => 'สั่นสะเทือนผิดปกติ',   'sort_order' => 6],
            ['value' => 'sparks_smoke',        'label_en' => 'Sparks / Smoke',       'label_th' => 'ประกายไฟ / ควัน',       'sort_order' => 7],
            ['value' => 'complete_breakdown',  'label_en' => 'Complete breakdown',   'label_th' => 'พังสิ้นเชิง',           'sort_order' => 8],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $failureModeList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $problemTypeList = LookupList::updateOrCreate(
            ['key' => 'maintenance_problem_type'],
            ['label_en' => 'Maintenance Problem Type', 'label_th' => 'ประเภทปัญหา', 'description' => 'หมวดหมู่ปัญหาของงานซ่อมเครื่องจักร', 'is_system' => false, 'is_active' => true, 'sort_order' => 11]
        );
        foreach ([
            ['value' => 'electrical',      'label_en' => 'Electrical',      'label_th' => 'ไฟฟ้า',        'sort_order' => 1],
            ['value' => 'mechanical',      'label_en' => 'Mechanical',      'label_th' => 'เครื่องกล',    'sort_order' => 2],
            ['value' => 'plumbing',        'label_en' => 'Plumbing',        'label_th' => 'ท่อ/ประปา',    'sort_order' => 3],
            ['value' => 'control_system',  'label_en' => 'Control System',  'label_th' => 'ระบบควบคุม',   'sort_order' => 4],
            ['value' => 'structural',      'label_en' => 'Structural',      'label_th' => 'โครงสร้าง',    'sort_order' => 5],
            ['value' => 'other',           'label_en' => 'Other',           'label_th' => 'อื่นๆ',        'sort_order' => 6],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $problemTypeList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        // ── 11. Form: ใบแจ้งซ่อมเครื่องจักร NTEQ ────────────
        $form = DocumentForm::updateOrCreate(
            ['form_key' => 'nteq_maintenance'],
            [
                'name' => 'ใบแจ้งซ่อมเครื่องจักร NTEQ',
                'document_type' => 'maintenance_request',
                'description' => 'ฟอร์มแจ้งซ่อม NTEQ Polymer — 18 fields, visibility rules, validation, 2-column layout',
                'is_active' => true,
                'layout_columns' => 2,
                'submission_table' => 'nteq_maintenance',
            ]
        );

        // Delete old fields and recreate
        $form->fields()->delete();

        // hazards / PPE / skills → migrated to DB-driven lookup lists (maintenance_hazard, maintenance_ppe, maintenance_skill)

        $fields = [
            // ── Section: ข้อมูลเอกสาร ──
            ['field_key' => 'reference_no',       'label_th' => 'เลขที่เอกสาร',       'label_en' => 'Reference No.',         'field_type' => 'auto_number', 'is_required' => false, 'sort_order' => 1],
            ['field_key' => 'document_date',      'label_th' => 'วันเอกสาร',          'label_en' => 'Document Date',          'field_type' => 'date',      'is_required' => true,  'sort_order' => 2, 'default_value' => 'today', 'is_readonly' => true],
            ['field_key' => 'priority',           'label_th' => 'ระดับความเร่งด่วน',  'label_en' => 'Priority',               'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 3,
                'options' => ['source' => 'maintenance_priority']],
            ['field_key' => 'emergency_reason',   'label_th' => 'เหตุผลฉุกเฉิน',      'label_en' => 'Emergency Reason',        'field_type' => 'textarea',  'is_required' => false, 'sort_order' => 4,
                'visibility_rules' => [['field' => 'priority', 'operator' => 'equals', 'value' => 'emergency']],
                'validation_rules' => ['min_length' => 10]],

            // ── Section: เครื่องจักร / ปัญหา ──
            ['field_key' => 'problem_type',       'label_th' => 'ประเภทปัญหา',        'label_en' => 'Problem Type',           'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 6,
                'options' => ['source' => 'maintenance_problem_type']],
            ['field_key' => 'description',        'label_th' => 'รายละเอียดปัญหา',    'label_en' => 'Problem Description',    'field_type' => 'textarea',  'is_required' => true,  'sort_order' => 7,
                'validation_rules' => ['min_length' => 20]],

            // ── Section: ลักษณะการเสีย (Failure) ──
            ['field_key' => 'section_failure',    'label_th' => 'ลักษณะการเสีย',     'label_en' => 'Failure Details',        'field_type' => 'section',   'is_required' => false, 'sort_order' => 8],
            ['field_key' => 'failure_mode',       'label_th' => 'ลักษณะการเสีย',     'label_en' => 'Failure Mode',           'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 9,
                'options' => ['source' => 'failure_mode']],
            ['field_key' => 'is_recurring',       'label_th' => 'ปัญหานี้เกิดซ้ำ?',  'label_en' => 'Is Recurring Issue?',    'field_type' => 'checkbox',  'is_required' => false, 'sort_order' => 10,
                'options' => ['เกิดซ้ำ']],

            // ── Section: วันเวลาที่พบ ──
            ['field_key' => 'section_extra',      'label_th' => 'ข้อมูลการพบปัญหา',   'label_en' => 'Discovery Info',        'field_type' => 'section',   'is_required' => false, 'sort_order' => 11],
            ['field_key' => 'found_date',         'label_th' => 'วันที่พบปัญหา',      'label_en' => 'Date Found',             'field_type' => 'date',      'is_required' => true,  'sort_order' => 12],
            ['field_key' => 'found_time',         'label_th' => 'เวลาที่พบ',          'label_en' => 'Time Found',             'field_type' => 'time',      'is_required' => false, 'sort_order' => 13],

            // ── Section: ผลกระทบ (Impact) ──
            ['field_key' => 'section_impact',     'label_th' => 'ผลกระทบ',           'label_en' => 'Impact Assessment',      'field_type' => 'section',   'is_required' => false, 'sort_order' => 14],
            ['field_key' => 'production_stopped', 'label_th' => 'สายผลิตหยุดหรือไม่', 'label_en' => 'Production Stopped',     'field_type' => 'checkbox',  'is_required' => false, 'sort_order' => 15,
                'options' => ['หยุดแล้ว']],
            ['field_key' => 'stop_duration',      'label_th' => 'ระยะเวลาหยุด (ชั่วโมง)', 'label_en' => 'Stop Duration (hours)', 'field_type' => 'number', 'is_required' => false, 'sort_order' => 16,
                'visibility_rules' => [['field' => 'production_stopped', 'operator' => 'equals', 'value' => 'หยุดแล้ว']],
                'validation_rules' => ['min' => 0, 'max' => 720]],
            ['field_key' => 'safety_impact',      'label_th' => 'ผลกระทบด้านความปลอดภัย', 'label_en' => 'Safety Impact',     'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 17,
                'options' => ['source' => 'impact_severity']],
            ['field_key' => 'quality_impact',     'label_th' => 'ผลกระทบต่อคุณภาพ',  'label_en' => 'Quality Impact',         'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 18,
                'options' => ['source' => 'impact_severity']],
            ['field_key' => 'environmental_impact', 'label_th' => 'ผลกระทบต่อสิ่งแวดล้อม', 'label_en' => 'Environmental Impact', 'field_type' => 'lookup',   'is_required' => true,  'sort_order' => 19,
                'options' => ['source' => 'impact_severity']],

            // ── Section: ความปลอดภัย (Safety / Permits) ──
            ['field_key' => 'section_safety',     'label_th' => 'ความปลอดภัย / ใบอนุญาต', 'label_en' => 'Safety / Permits',   'field_type' => 'section',   'is_required' => false, 'sort_order' => 20],
            ['field_key' => 'loto_required',      'label_th' => 'ต้อง LOTO (Lock-Out Tag-Out)', 'label_en' => 'LOTO Required', 'field_type' => 'checkbox', 'is_required' => false, 'sort_order' => 21,
                'options' => ['ต้อง LOTO']],
            ['field_key' => 'hot_work_permit',    'label_th' => 'ต้อง Hot Work Permit (ตัด/เชื่อม/ไฟ)', 'label_en' => 'Hot Work Permit', 'field_type' => 'checkbox', 'is_required' => false, 'sort_order' => 22,
                'options' => ['ต้อง Hot Work Permit']],
            ['field_key' => 'confined_space',     'label_th' => 'ที่อับอากาศ (Confined Space)', 'label_en' => 'Confined Space Permit', 'field_type' => 'checkbox', 'is_required' => false, 'sort_order' => 23,
                'options' => ['ต้อง Confined Space Permit']],
            ['field_key' => 'hazards_present',    'label_th' => 'อันตรายที่มีอยู่',   'label_en' => 'Hazards Present',        'field_type' => 'multi_select', 'is_required' => false, 'sort_order' => 24,
                'options' => ['source' => 'maintenance_hazard']],
            ['field_key' => 'ppe_required',       'label_th' => 'อุปกรณ์ป้องกันส่วนบุคคล (PPE)', 'label_en' => 'PPE Required', 'field_type' => 'multi_select', 'is_required' => false, 'sort_order' => 25,
                'options' => ['source' => 'maintenance_ppe']],

            // ── Section: ทรัพยากร (Resources) ──
            ['field_key' => 'section_resources',  'label_th' => 'ทรัพยากรที่ต้องใช้',  'label_en' => 'Resources',              'field_type' => 'section',   'is_required' => false, 'sort_order' => 26],
            ['field_key' => 'skills_needed',      'label_th' => 'ทักษะช่างที่ต้องใช้', 'label_en' => 'Skills Needed',         'field_type' => 'multi_select', 'is_required' => false, 'sort_order' => 27,
                'options' => ['source' => 'maintenance_skill']],
            ['field_key' => 'estimated_repair_hours', 'label_th' => 'ประมาณเวลาซ่อม (ชั่วโมง)', 'label_en' => 'Estimated Repair Hours', 'field_type' => 'number', 'is_required' => false, 'sort_order' => 28,
                'validation_rules' => ['min' => 0, 'max' => 168]],
            ['field_key' => 'requested_completion_date', 'label_th' => 'ต้องการให้ซ่อมเสร็จก่อน', 'label_en' => 'Requested Completion Date', 'field_type' => 'date', 'is_required' => false, 'sort_order' => 29],
            ['field_key' => 'estimated_cost',     'label_th' => 'ประมาณค่าซ่อม (บาท)', 'label_en' => 'Estimated Cost (THB)',  'field_type' => 'currency',  'is_required' => false, 'sort_order' => 30,
                'validation_rules' => ['min' => 0, 'max' => 500000]],
            ['field_key' => 'needs_parts',        'label_th' => 'ต้องการอะไหล่',      'label_en' => 'Requires Spare Parts',   'field_type' => 'checkbox',  'is_required' => false, 'sort_order' => 31,
                'options' => ['ต้องการ']],
            ['field_key' => 'parts_list',         'label_th' => 'รายการอะไหล่ที่ต้องใช้', 'label_en' => 'Required Parts',     'field_type' => 'table',     'is_required' => false, 'sort_order' => 32, 'col_span' => 2,
                'options' => ['columns' => [
                    ['key' => 'spare_part', 'label' => 'อะไหล่',     'type' => 'text'],
                    ['key' => 'qty',        'label' => 'จำนวน',      'type' => 'number'],
                    ['key' => 'unit',       'label' => 'หน่วย',      'type' => 'text'],
                    ['key' => 'remark',     'label' => 'หมายเหตุ',   'type' => 'text'],
                ]],
                'visibility_rules' => [['field' => 'needs_parts', 'operator' => 'equals', 'value' => 'ต้องการ']]],

            // ── Section: Attachments + Sign-off ──
            ['field_key' => 'section_attach',     'label_th' => 'แนบรูป / ลงชื่อ',    'label_en' => 'Attachments & Sign-off', 'field_type' => 'section',   'is_required' => false, 'sort_order' => 33],
            ['field_key' => 'photos',             'label_th' => 'ภาพถ่ายจุดเสียหาย',  'label_en' => 'Damage Photos',          'field_type' => 'multi_file', 'is_required' => false, 'sort_order' => 34, 'col_span' => 2],
            ['field_key' => 'reporter_phone',     'label_th' => 'เบอร์โทรผู้แจ้ง',    'label_en' => 'Reporter Phone',         'field_type' => 'phone',     'is_required' => false, 'sort_order' => 35],
            ['field_key' => 'reporter_signature', 'label_th' => 'ลายมือชื่อผู้แจ้ง',  'label_en' => 'Reporter Signature',     'field_type' => 'signature', 'is_required' => false, 'sort_order' => 36],
        ];

        // Pre-enable is_searchable on high-signal fields so the user's list filter
        // bar is useful immediately after seeding — no need for admin to tick them.
        $searchableDefaults = ['document_date', 'priority', 'problem_type', 'failure_mode', 'is_recurring', 'production_stopped', 'safety_impact', 'found_date'];
        foreach ($fields as $f) {
            $form->fields()->create([
                'field_key' => $f['field_key'],
                'label' => $f['label'] ?? $f['label_th'] ?? $f['label_en'] ?? '',
                'label_en' => $f['label_en'] ?? null,
                'label_th' => $f['label_th'] ?? null,
                'field_type' => $f['field_type'],
                'is_required' => $f['is_required'],
                'is_searchable' => in_array($f['field_key'], $searchableDefaults, true),
                'sort_order' => $f['sort_order'],
                'col_span' => $f['col_span'] ?? 0,
                'placeholder' => $f['placeholder'] ?? null,
                'default_value' => $f['default_value'] ?? null,
                'is_readonly' => (bool) ($f['is_readonly'] ?? false),
                'options' => $f['options'] ?? null,
                'visibility_rules' => $f['visibility_rules'] ?? null,
                'validation_rules' => $f['validation_rules'] ?? null,
            ]);
        }

        // Create dedicated submission table. Drop first so re-running the seeder
        // picks up any field changes (createTable is idempotent and skips if the
        // table already exists).
        if ($form->submission_table) {
            Schema::dropIfExists($form->submission_table);
        }
        app(FormSchemaService::class)->createTable($form->load('fields'));

        // Global workflow policy for the form
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            ['use_amount_condition' => false, 'workflow_id' => $wfMaint->id]
        );

        // ── 12. Running Number ─────────────────────────────
        RunningNumberConfig::updateOrCreate(
            ['document_type' => 'maintenance_request'],
            [
                'prefix' => 'MR',
                'digit_count' => 5,
                'reset_mode' => 'yearly',
                'include_year' => true,
                'include_month' => true,
                'is_active' => true,
            ]
        );
        $this->command?->info('Running Number: MR + year + month + 5 digits (e.g. MR202504-00001)');

        $this->command?->info('Form: nteq_maintenance — 18 fields, fdata table created, workflow policy set');

        // Dashboard with CMMS-flavored widgets (repair_requests data source)
        $this->call(FactoryDashboardSeeder::class);

        $this->command?->info('');
        $this->command?->info('✓ NTEQ Polymer demo ready!');
        $this->command?->info('  Login accounts:');
        $this->command?->info('    admin@example.com   (super-admin / ผจก.โรงงาน)');
        $this->command?->info('    somchai@nteq.test   (พนักงานผลิต — requester)');
        $this->command?->info('    somsri@nteq.test    (หัวหน้ากะ — approver step 1)');
        $this->command?->info('    pranee@nteq.test    (ผจก.แผนก PROD  — approver step 2 quorum 2/3)');
        $this->command?->info('    manop@nteq.test     (ผจก.แผนก MAINT — approver step 2 quorum 2/3)');
        $this->command?->info('    suda@nteq.test      (ผจก.แผนก QC    — approver step 2 quorum 2/3)');
        $this->command?->info('    somkit@nteq.test    (ผจก.โรงงาน — approver step 3)');
        $this->command?->info('  Password: Nteq1234!');
    }
}
