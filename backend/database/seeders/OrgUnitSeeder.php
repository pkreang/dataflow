<?php

namespace Database\Seeders;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates a minimal school org hierarchy and assigns org_unit_id to demo users.
 * Must run AFTER DemoPeopleSeeder (needs users to exist).
 *
 *   php artisan db:seed --class=OrgUnitSeeder
 */
class OrgUnitSeeder extends Seeder
{
    public function run(): void
    {
        $gm       = User::where('email', 'gm@demo.com')->first();
        $manager  = User::where('email', 'manager@demo.com')->first();

        // Root: โรงเรียนตัวอย่าง
        $root = OrgUnit::updateOrCreate(
            ['name' => 'โรงเรียนตัวอย่าง'],
            [
                'type'         => 'company',
                'parent_id'    => null,
                'head_user_id' => $gm?->id,
                'sort_order'   => 1,
                'is_active'    => true,
            ]
        );

        // Child 1: ฝ่ายวิชาการ
        $acad = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายวิชาการ'],
            [
                'type'         => 'department',
                'parent_id'    => $root->id,
                'head_user_id' => $manager?->id,
                'sort_order'   => 1,
                'is_active'    => true,
            ]
        );

        // Child 2: ฝ่ายธุรการ
        $admin = OrgUnit::updateOrCreate(
            ['name' => 'ฝ่ายธุรการ'],
            [
                'type'         => 'department',
                'parent_id'    => $root->id,
                'head_user_id' => null,
                'sort_order'   => 2,
                'is_active'    => true,
            ]
        );

        // Assign org_unit_id to demo users
        $assignments = [
            'employee@demo.com'       => $acad->id,
            'manager@demo.com'        => $acad->id,
            'gm@demo.com'             => $root->id,
            'admin.staff@demo.com'    => $admin->id,
            'finance@demo.com'        => $admin->id,
            'facility@demo.com'       => $admin->id,
        ];

        foreach ($assignments as $email => $orgUnitId) {
            User::where('email', $email)->update(['org_unit_id' => $orgUnitId]);
        }

        // Also assign department-generated demo users (demo.sch_*@demo.com) to acad
        User::where('email', 'like', 'demo.sch_%@demo.com')
            ->whereNull('org_unit_id')
            ->update(['org_unit_id' => $acad->id]);
    }
}
