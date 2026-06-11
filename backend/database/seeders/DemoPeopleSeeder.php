<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Demo users for school eForm / approval workflow testing.
 *
 * One submitter (viewer) per SCH_* department + two position-based approvers.
 * Password for all demo accounts: demo1234
 *
 * | Email | แผนก | ตำแหน่ง | Role |
 * |-------|------|---------|------|
 * | employee@demo.com | ฝ่ายวิชาการ | ครู/บุคลากรวิชาการ | viewer |
 * | admin.staff@demo.com | ฝ่ายธุรการ | นักวิชาการธุรการ | viewer |
 * | finance@demo.com | ฝ่ายการเงิน | นักการเงินและบัญชี | viewer |
 * | facility@demo.com | ฝ่ายอาคารและสถานที่ | ครู/บุคลากรวิชาการ | viewer |
 * | manager@demo.com | ฝ่ายวิชาการ | หัวหน้าฝ่ายวิชาการ | approver (ขั้นที่ 1 workflow) |
 * | gm@demo.com | — | รองผู้อำนวยการ | approver (ขั้นที่ 2 workflow) |
 *
 * Optional CMMS workflows use `FactoryPositionSeeder` (MAINT_SUP, …), not school departments.
 */
class DemoPeopleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, RolePermissionSeeder::class]);

        $employeeRole = Role::where('name', 'employee')->first();
        $approverRole = Role::where('name', 'approver')->first();

        $positionsByCode = Position::query()
            ->whereIn('code', [
                'SCH_TEACHER',
                'SCH_ACAD_HEAD',
                'SCH_VICE_PRINCIPAL',
                'SCH_ADMIN_OFFICER',
                'SCH_FIN_OFFICER',
            ])
            ->get()
            ->keyBy('code');

        $company = Company::first();

        $departments = Department::query()
            ->where('code', 'like', 'SCH_%')
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        /** @var array<string, array{email: string, first_name: string, last_name: string, position_code: string}> */
        $submitterProfiles = [
            'SCH_ACAD' => [
                'email' => 'employee@demo.com',
                'first_name' => 'สมชาย',
                'last_name' => 'ใจดี',
                'position_code' => 'SCH_TEACHER',
            ],
            'SCH_ADM' => [
                'email' => 'admin.staff@demo.com',
                'first_name' => 'ประไพ',
                'last_name' => 'ธุรการ',
                'position_code' => 'SCH_ADMIN_OFFICER',
            ],
            'SCH_FIN' => [
                'email' => 'finance@demo.com',
                'first_name' => 'มาลี',
                'last_name' => 'การเงิน',
                'position_code' => 'SCH_FIN_OFFICER',
            ],
            'SCH_FAC' => [
                'email' => 'facility@demo.com',
                'first_name' => 'วิทยา',
                'last_name' => 'อาคารสถานที่',
                'position_code' => 'SCH_TEACHER',
            ],
        ];

        foreach ($departments as $code => $dept) {
            $profile = $submitterProfiles[$code] ?? [
                'email' => 'demo.'.strtolower($code).'@demo.com',
                'first_name' => $dept->name,
                'last_name' => 'ทดสอบ',
                'position_code' => 'SCH_TEACHER',
            ];

            $pos = $positionsByCode->get($profile['position_code']);
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                [
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                    'password' => 'demo1234',
                    'password_changed_at' => now(),
                    'password_must_change' => false,
                    'is_active' => true,
                    'is_super_admin' => false,
                    'company_id' => $company?->id,
                    'department_id' => $dept->id,
                    'position_id' => $pos?->id,
                ]
            );

            if ($employeeRole) {
                $user->syncRoles([$employeeRole]);
            }
        }

        $acadHead = $positionsByCode->get('SCH_ACAD_HEAD');
        $vice = $positionsByCode->get('SCH_VICE_PRINCIPAL');
        $acadDept = $departments->get('SCH_ACAD');

        $approvers = [
            [
                'email' => 'manager@demo.com',
                'first_name' => 'สมศรี',
                'last_name' => 'มีอำนาจ',
                'role' => $approverRole,
                'position_id' => $acadHead?->id,
                'department_id' => $acadDept?->id,
            ],
            [
                'email' => 'gm@demo.com',
                'first_name' => 'วิชัย',
                'last_name' => 'บริหาร',
                'role' => $approverRole,
                'position_id' => $vice?->id,
                'department_id' => null,
            ],
        ];

        foreach ($approvers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => 'demo1234',
                    'password_changed_at' => now(),
                    'password_must_change' => false,
                    'is_active' => true,
                    'is_super_admin' => false,
                    'company_id' => $company?->id,
                    'department_id' => $data['department_id'],
                    'position_id' => $data['position_id'],
                ]
            );

            if ($data['role']) {
                $user->syncRoles([$data['role']]);
            }
        }

        $nSubmitters = $departments->count();
        $this->command?->info("DemoPeopleSeeder: {$nSubmitters} submitters (one per SCH_* dept) + 2 approvers (password: demo1234).");
    }
}
