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
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\PmPlan;
use App\Models\PmTaskItem;
use App\Models\Position;
use App\Models\RunningNumberConfig;
use App\Models\SparePart;
use App\Models\User;
use App\Services\FormSchemaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * NTEQ Polymer Co., Ltd. — โรงงานแปรรูปยางพารา จ.มุกดาหาร
 *
 * Seeds: company, departments (7), positions (8), users (8+admin),
 * equipment categories + locations + equipment, document type (maintenance_request),
 * 3-step approval workflow, maintenance form with 18 fields (visibility rules + validation),
 * and department workflow bindings.
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

        // ── 3. Departments (7) ──────────────────────────────
        $departments = [
            ['code' => 'PROD',  'name' => 'ฝ่ายผลิต',                        'description' => 'สายผลิตยางแท่ง STR/MVC — กำลังผลิต 72,000 ตัน/ปี'],
            ['code' => 'MAINT', 'name' => 'ฝ่ายซ่อมบำรุง',                    'description' => 'ดูแลเครื่องจักร ระบบไฟฟ้า ระบบควบคุม'],
            ['code' => 'QC',    'name' => 'ฝ่ายควบคุมคุณภาพ',                  'description' => 'ห้องแล็บ ทดสอบ Mooney/PRI/Plasticity — ISO/IEC 17025'],
            ['code' => 'WH',    'name' => 'ฝ่ายคลังสินค้า',                    'description' => 'วัตถุดิบ (ยางก้อน/ยางแผ่น) + สินค้าสำเร็จ (STR bales)'],
            ['code' => 'PROC',  'name' => 'ฝ่ายจัดซื้อ',                      'description' => 'จัดซื้อวัตถุดิบ อะไหล่ วัสดุสิ้นเปลือง'],
            ['code' => 'EHS',   'name' => 'ฝ่ายความปลอดภัยและสิ่งแวดล้อม',    'description' => 'ISO 14001, EcoVadis, บ่อบำบัดน้ำเสีย, จป.'],
            ['code' => 'MGMT',  'name' => 'ฝ่ายบริหาร',                       'description' => 'ผู้บริหาร บัญชี HR'],
        ];

        $deptMap = [];
        foreach ($departments as $d) {
            $dept = Department::updateOrCreate(['code' => $d['code']], ['name' => $d['name'], 'description' => $d['description']]);
            $deptMap[$d['code']] = $dept;
        }
        $this->command?->info('Departments: '.count($departments));

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
                    'department_id' => $deptMap[$u['dept']]->id,
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
                'department_id' => $deptMap['MGMT']->id,
                'position_id' => $posMap['PLANT_MGR']->id,
                'company_id' => $company->id,
            ]);
        }
        $this->command?->info('Users: '.count($users).' + admin updated');

        // ── 6. Equipment Categories ─────────────────────────
        $categories = [
            ['code' => 'SHRED',   'name' => 'Shredder / เครื่องสับยาง',        'description' => 'เครื่องสับย่อยยางก้อน'],
            ['code' => 'CREPER',  'name' => 'Creper / เครื่องรีดยาง',          'description' => 'เครื่องรีดแผ่นยาง'],
            ['code' => 'DRYER',   'name' => 'Dryer / เตาอบยาง',              'description' => 'เตาอบยางแท่ง STR'],
            ['code' => 'BALER',   'name' => 'Baling Press / เครื่องอัดก้อน',    'description' => 'เครื่องอัดยางเป็นก้อน 35 kg'],
            ['code' => 'CONV',    'name' => 'Conveyor / สายพานลำเลียง',       'description' => 'สายพานลำเลียงวัตถุดิบ/สินค้า'],
            ['code' => 'BOILER',  'name' => 'Boiler / หม้อไอน้ำ',             'description' => 'หม้อไอน้ำสำหรับเตาอบ'],
            ['code' => 'WWT',     'name' => 'Wastewater Treatment / ระบบบำบัดน้ำเสีย', 'description' => 'ระบบบำบัดน้ำเสียจากกระบวนการผลิต'],
            ['code' => 'FKLIFT',  'name' => 'Forklift / รถยก',               'description' => 'รถยกคลังสินค้า'],
            ['code' => 'LABINST', 'name' => 'Lab Instrument / เครื่องมือแล็บ',  'description' => 'Mooney Viscometer, Plasticity Tester'],
        ];

        $catMap = [];
        foreach ($categories as $c) {
            $cat = EquipmentCategory::updateOrCreate(['code' => $c['code']], ['name' => $c['name'], 'description' => $c['description'], 'is_active' => true]);
            $catMap[$c['code']] = $cat;
        }

        // ── 7. Equipment Locations ──────────────────────────
        $locations = [
            ['code' => 'PROD_A1', 'name' => 'อาคาร A สายผลิต 1',    'building' => 'อาคาร A', 'floor' => '1', 'zone' => 'สายผลิต STR10'],
            ['code' => 'PROD_A2', 'name' => 'อาคาร A สายผลิต 2',    'building' => 'อาคาร A', 'floor' => '1', 'zone' => 'สายผลิต STR20'],
            ['code' => 'DRY_B',   'name' => 'อาคาร B เครื่องอบ',     'building' => 'อาคาร B', 'floor' => '1', 'zone' => 'Drying'],
            ['code' => 'PACK_C',  'name' => 'อาคาร C บรรจุ',        'building' => 'อาคาร C', 'floor' => '1', 'zone' => 'Packing/Baling'],
            ['code' => 'WH_D',    'name' => 'คลังสินค้า D',          'building' => 'อาคาร D', 'floor' => '1', 'zone' => 'Warehouse'],
            ['code' => 'WWT_OUT', 'name' => 'บ่อบำบัดน้ำเสีย',       'building' => 'Outdoor', 'floor' => null, 'zone' => 'Wastewater'],
            ['code' => 'UTIL',    'name' => 'โรงไฟฟ้า/หม้อไอน้ำ',     'building' => 'Utility', 'floor' => null, 'zone' => 'Utilities'],
            ['code' => 'LAB',     'name' => 'ห้องปฏิบัติการ QC',       'building' => 'อาคาร A', 'floor' => '2', 'zone' => 'Laboratory'],
        ];

        $locMap = [];
        foreach ($locations as $l) {
            $loc = EquipmentLocation::updateOrCreate(['code' => $l['code']], ['name' => $l['name'], 'building' => $l['building'], 'floor' => $l['floor'], 'zone' => $l['zone'], 'is_active' => true]);
            $locMap[$l['code']] = $loc;
        }

        // ── 8. Equipment (sample) ───────────────────────────
        $equipmentList = [
            ['code' => 'SHRED-001', 'name' => 'Shredder #1',        'cat' => 'SHRED',   'loc' => 'PROD_A1', 'serial' => 'SH-2018-001', 'manufacturer' => 'Weiler',            'model' => 'WEL-200XL',    'criticality' => 'A', 'purchase_date' => '2018-03-15', 'runtime_hours' => 38240.50],
            ['code' => 'SHRED-002', 'name' => 'Shredder #2',        'cat' => 'SHRED',   'loc' => 'PROD_A2', 'serial' => 'SH-2018-002', 'manufacturer' => 'Weiler',            'model' => 'WEL-200XL',    'criticality' => 'A', 'purchase_date' => '2018-09-01', 'runtime_hours' => 35120.00],
            ['code' => 'CREP-001',  'name' => 'Creper Line 1',      'cat' => 'CREPER',  'loc' => 'PROD_A1', 'serial' => 'CR-2018-001', 'manufacturer' => 'Siam Rubber Eng.',  'model' => 'SRE-CREP-75',  'criticality' => 'A', 'purchase_date' => '2018-06-10', 'runtime_hours' => 36990.75],
            ['code' => 'DRY-001',   'name' => 'Dryer Oven #1',      'cat' => 'DRYER',   'loc' => 'DRY_B',   'serial' => 'DR-2019-001', 'manufacturer' => 'Tecnocoque',         'model' => 'TC-DRY-1200',  'criticality' => 'A', 'purchase_date' => '2019-02-20', 'runtime_hours' => 29880.00],
            ['code' => 'DRY-002',   'name' => 'Dryer Oven #2',      'cat' => 'DRYER',   'loc' => 'DRY_B',   'serial' => 'DR-2019-002', 'manufacturer' => 'Tecnocoque',         'model' => 'TC-DRY-1200',  'criticality' => 'A', 'purchase_date' => '2019-08-05', 'runtime_hours' => 27540.25],
            ['code' => 'BALER-001', 'name' => 'Baling Press #1',    'cat' => 'BALER',   'loc' => 'PACK_C',  'serial' => 'BP-2018-001', 'manufacturer' => 'Bollegraaf',        'model' => 'HBC-50',       'criticality' => 'B', 'purchase_date' => '2018-07-22', 'runtime_hours' => 31200.00],
            ['code' => 'CONV-001',  'name' => 'Conveyor Belt A1',   'cat' => 'CONV',    'loc' => 'PROD_A1', 'serial' => 'CB-2018-001', 'manufacturer' => 'Thai Conveyor Co.', 'model' => 'TCC-BLT-600',  'criticality' => 'B', 'purchase_date' => '2018-04-01', 'runtime_hours' => 42360.00],
            ['code' => 'CONV-002',  'name' => 'Conveyor Belt A2',   'cat' => 'CONV',    'loc' => 'PROD_A2', 'serial' => 'CB-2018-002', 'manufacturer' => 'Thai Conveyor Co.', 'model' => 'TCC-BLT-600',  'criticality' => 'B', 'purchase_date' => '2018-10-15', 'runtime_hours' => 40120.50],
            ['code' => 'BOIL-001',  'name' => 'Boiler #1',          'cat' => 'BOILER',  'loc' => 'UTIL',    'serial' => 'BL-2017-001', 'manufacturer' => 'Bosch Thermotechnik', 'model' => 'BTH-STM-5T',  'criticality' => 'A', 'purchase_date' => '2017-11-30', 'runtime_hours' => 48790.00],
            ['code' => 'WWT-001',   'name' => 'Aeration Tank #1',   'cat' => 'WWT',     'loc' => 'WWT_OUT', 'serial' => 'WT-2018-001', 'manufacturer' => 'Siemens',           'model' => 'AER-AT-200',   'criticality' => 'B', 'purchase_date' => '2018-05-18', 'runtime_hours' => 39860.25],
            ['code' => 'FK-001',    'name' => 'Forklift Toyota 3T', 'cat' => 'FKLIFT',  'loc' => 'WH_D',    'serial' => 'FL-2020-001', 'manufacturer' => 'Toyota',            'model' => '8FGU30',       'criticality' => 'C', 'purchase_date' => '2020-01-10', 'runtime_hours' => 8420.75],
            ['code' => 'MV-001',    'name' => 'Mooney Viscometer',  'cat' => 'LABINST', 'loc' => 'LAB',     'serial' => 'MV-2019-001', 'manufacturer' => 'Alpha Technologies', 'model' => 'MV2000VS',     'criticality' => 'C', 'purchase_date' => '2019-04-25', 'runtime_hours' => 6150.00],
        ];

        foreach ($equipmentList as $eq) {
            Equipment::updateOrCreate(
                ['code' => $eq['code']],
                [
                    'name' => $eq['name'],
                    'equipment_category_id' => $catMap[$eq['cat']]->id,
                    'equipment_location_id' => $locMap[$eq['loc']]->id,
                    'company_id' => $company->id,
                    'serial_number' => $eq['serial'],
                    'manufacturer' => $eq['manufacturer'],
                    'model' => $eq['model'],
                    'status' => 'active',
                    'criticality' => $eq['criticality'],
                    'purchase_date' => $eq['purchase_date'],
                    'runtime_hours' => $eq['runtime_hours'],
                ]
            );
        }
        $this->command?->info('Equipment: '.count($equipmentList).' items');

        // ── 8b. Spare Parts ─────────────────────────────────
        $spareParts = [
            ['code' => 'CB-001',   'name' => 'สายพาน Conveyor Belt 500mm', 'unit_cost' => 8500,  'current_stock' => 5,  'min_stock' => 2],
            ['code' => 'BRG-6205', 'name' => 'ลูกปืนแบริ่ง 6205-2RS',      'unit_cost' => 350,   'current_stock' => 20, 'min_stock' => 10],
            ['code' => 'BRG-6208', 'name' => 'ลูกปืนแบริ่ง 6208-2RS',      'unit_cost' => 550,   'current_stock' => 15, 'min_stock' => 5],
            ['code' => 'SEAL-001', 'name' => 'ซีลกันน้ำมัน Oil Seal',       'unit_cost' => 180,   'current_stock' => 30, 'min_stock' => 10],
            ['code' => 'BLD-001',  'name' => 'ใบมีด Shredder Blade',       'unit_cost' => 2500,  'current_stock' => 8,  'min_stock' => 4],
            ['code' => 'FLT-001',  'name' => 'ไส้กรองน้ำมันไฮดรอลิก',       'unit_cost' => 450,   'current_stock' => 12, 'min_stock' => 5],
            ['code' => 'VBT-001',  'name' => 'สายพาน V-Belt B68',         'unit_cost' => 320,   'current_stock' => 10, 'min_stock' => 5],
            ['code' => 'GRS-001',  'name' => 'จาระบี Grease EP2 (15kg)',   'unit_cost' => 1200,  'current_stock' => 6,  'min_stock' => 3],
        ];

        foreach ($spareParts as $sp) {
            SparePart::updateOrCreate(
                ['code' => $sp['code']],
                ['name' => $sp['name'], 'unit_cost' => $sp['unit_cost'], 'current_stock' => $sp['current_stock'], 'min_stock' => $sp['min_stock'], 'is_active' => true]
            );
        }
        $this->command?->info('Spare Parts: '.count($spareParts).' items');

        // ── 8c. PM Plans (Phase 2A demo) ────────────────────
        $eqMap = Equipment::whereIn('code', ['BOIL-001', 'SHRED-001', 'DRY-001'])->pluck('id', 'code');
        $spMap = SparePart::whereIn('code', ['GRS-001', 'BLD-001', 'VBT-001'])->pluck('id', 'code');

        $pmPlans = [
            [
                'equipment' => 'BOIL-001', 'name' => 'ตรวจเช็ค Boiler รายเดือน',
                'description' => 'ตรวจประจำเดือนตามมาตรฐานความปลอดภัยของหม้อไอน้ำ',
                'frequency_type' => 'date', 'interval_days' => 30, 'estimated_duration_minutes' => 60,
                'tasks' => [
                    ['description' => 'ตรวจ pressure gauge', 'task_type' => 'measurement', 'expected_value' => '8-12', 'unit' => 'bar', 'requires_photo' => true, 'estimated_minutes' => 5, 'is_critical' => true],
                    ['description' => 'ตรวจ safety valve ไม่รั่วและ release ได้ปกติ', 'task_type' => 'visual', 'requires_photo' => true, 'estimated_minutes' => 10, 'is_critical' => true],
                    ['description' => 'หล่อลื่น bearing ของ fire pump', 'task_type' => 'lubrication', 'spare_part_code' => 'GRS-001', 'estimated_minutes' => 15, 'loto_required' => true],
                    ['description' => 'ทำความสะอาด strainer น้ำเข้า', 'task_type' => 'cleaning', 'estimated_minutes' => 20, 'loto_required' => true],
                    ['description' => 'บันทึกชั่วโมงใช้งาน', 'task_type' => 'measurement', 'unit' => 'hr', 'estimated_minutes' => 2],
                ],
            ],
            [
                'equipment' => 'SHRED-001', 'name' => 'เปลี่ยนใบมีด Shredder ทุก 500 ชม.',
                'description' => 'เปลี่ยนใบมีดเมื่อสะสม runtime ครบ 500 ชั่วโมง — ถ้าใบสึกผิดปกติก่อนครบรอบ ให้เปลี่ยนก่อนกำหนด',
                'frequency_type' => 'runtime', 'interval_hours' => 500, 'estimated_duration_minutes' => 90,
                'tasks' => [
                    ['description' => 'ตรวจสภาพใบมีดและวัดระดับสึก', 'task_type' => 'visual', 'requires_photo' => true, 'estimated_minutes' => 10, 'is_critical' => true],
                    ['description' => 'เปลี่ยนใบมีด (ชุดครบ)', 'task_type' => 'replacement', 'spare_part_code' => 'BLD-001', 'estimated_minutes' => 45, 'loto_required' => true, 'requires_photo' => true, 'requires_signature' => true, 'is_critical' => true],
                    ['description' => 'ทดสอบเดินเครื่องเปล่า วัดเสียงรบกวน', 'task_type' => 'measurement', 'expected_value' => '< 70', 'unit' => 'dB', 'estimated_minutes' => 10],
                    ['description' => 'บันทึกรอบใบมีดใหม่', 'task_type' => 'measurement', 'unit' => 'rpm', 'estimated_minutes' => 5],
                ],
            ],
            [
                'equipment' => 'DRY-001', 'name' => 'Overhaul Dryer ทุกไตรมาส',
                'description' => 'งานใหญ่รายไตรมาส — หยุดสายผลิต 4-6 ชั่วโมง',
                'frequency_type' => 'date', 'interval_days' => 90, 'estimated_duration_minutes' => 240,
                'tasks' => [
                    ['description' => 'ตรวจ blower belt ยึดแน่นและไม่สึก', 'task_type' => 'visual', 'requires_photo' => true, 'estimated_minutes' => 10, 'is_critical' => true],
                    ['description' => 'เปลี่ยน V-belt blower', 'task_type' => 'replacement', 'spare_part_code' => 'VBT-001', 'estimated_minutes' => 30, 'loto_required' => true],
                    ['description' => 'หล่อลื่นมอเตอร์ blower', 'task_type' => 'lubrication', 'spare_part_code' => 'GRS-001', 'estimated_minutes' => 15],
                    ['description' => 'ทำความสะอาด heat exchanger', 'task_type' => 'cleaning', 'estimated_minutes' => 60, 'loto_required' => true],
                    ['description' => 'ตรวจ temperature sensor', 'task_type' => 'measurement', 'expected_value' => '±2', 'unit' => '°C', 'estimated_minutes' => 10],
                    ['description' => 'ตรวจ air flow', 'task_type' => 'measurement', 'expected_value' => '8-12', 'unit' => 'm/s', 'requires_photo' => true, 'estimated_minutes' => 10],
                ],
            ],
        ];

        foreach ($pmPlans as $p) {
            $plan = PmPlan::updateOrCreate(
                ['equipment_id' => $eqMap[$p['equipment']], 'name' => $p['name']],
                [
                    'description' => $p['description'],
                    'frequency_type' => $p['frequency_type'],
                    'interval_days' => $p['interval_days'] ?? null,
                    'interval_hours' => $p['interval_hours'] ?? null,
                    'estimated_duration_minutes' => $p['estimated_duration_minutes'],
                    'is_active' => true,
                ]
            );
            $plan->taskItems()->delete();
            foreach ($p['tasks'] as $idx => $t) {
                PmTaskItem::create([
                    'pm_plan_id' => $plan->id,
                    'step_no' => $idx + 1,
                    'sort_order' => $idx + 1,
                    'description' => $t['description'],
                    'task_type' => $t['task_type'],
                    'expected_value' => $t['expected_value'] ?? null,
                    'unit' => $t['unit'] ?? null,
                    'requires_photo' => $t['requires_photo'] ?? false,
                    'requires_signature' => $t['requires_signature'] ?? false,
                    'spare_part_id' => isset($t['spare_part_code']) ? ($spMap[$t['spare_part_code']] ?? null) : null,
                    'estimated_minutes' => $t['estimated_minutes'] ?? null,
                    'loto_required' => $t['loto_required'] ?? false,
                    'is_critical' => $t['is_critical'] ?? false,
                ]);
            }
        }
        $this->command?->info('PM Plans: '.count($pmPlans).' plans seeded');

        // Generate demo WOs for the 2 date-based plans (SHRED runtime-based needs next_due_runtime set)
        $generator = app(\App\Services\Cmms\PmWorkOrderGenerator::class);
        foreach (PmPlan::whereIn('equipment_id', [$eqMap['BOIL-001'], $eqMap['DRY-001']])->get() as $plan) {
            $generator->generate($plan);
        }
        $this->command?->info('PM Work Orders: '.\App\Models\PmWorkOrder::count().' demo WOs generated');

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

        // ── 10. Department ↔ Workflow Binding ────────────────
        foreach (['PROD', 'MAINT', 'QC', 'WH'] as $deptCode) {
            DepartmentWorkflowBinding::updateOrCreate(
                ['department_id' => $deptMap[$deptCode]->id, 'document_type' => 'maintenance_request'],
                ['workflow_id' => $wfMaint->id]
            );
        }
        $this->command?->info('Workflow bindings: 4 departments → maintenance workflow');

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

            // ── Section: เครื่องจักร ──
            ['field_key' => 'equipment_id',       'label_th' => 'เครื่องจักร',        'label_en' => 'Equipment',              'field_type' => 'lookup',    'is_required' => true,  'sort_order' => 5,
                'options' => ['source' => 'equipment']],
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
                    ['key' => 'spare_part', 'label' => 'อะไหล่',     'type' => 'lookup', 'lookup_source' => 'spare_part'],
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
        $searchableDefaults = ['document_date', 'priority', 'equipment_id', 'problem_type', 'failure_mode', 'is_recurring', 'production_stopped', 'safety_impact', 'found_date'];
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
            ['form_id' => $form->id, 'department_id' => null],
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
