<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Seeds the school-vertical positions (SCH_*). Invoked from school flows only
 * (`IndustryTemplateSeeder`, `DevelopmentDemoSeeder`, `SchoolWorkflowTestUsersCommand`)
 * — never from `DatabaseSeeder`, so factory deployments don't end up with school
 * positions sitting alongside their factory positions (MAINT_SUP, PLANT_MGR, etc.).
 *
 * Idempotent (updateOrCreate by code). The previous version also deleted
 * legacy CMMS rows; that cleanup is gone because each vertical now seeds its
 * own positions explicitly and they no longer collide.
 */
class PositionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['code' => 'SCH_TEACHER', 'name' => 'ครู / บุคลากรวิชาการ', 'description' => 'Teacher or academic staff — typical form submitter'],
            ['code' => 'SCH_ACAD_HEAD', 'name' => 'หัวหน้าฝ่ายวิชาการ', 'description' => 'Head of academic affairs — first-line academic approval'],
            ['code' => 'SCH_VICE_PRINCIPAL', 'name' => 'รองผู้อำนวยการ', 'description' => 'Vice principal — school-wide approval level'],
            ['code' => 'SCH_ADMIN_OFFICER', 'name' => 'นักวิชาการธุรการ', 'description' => 'Administrative officer — procurement / general affairs'],
            ['code' => 'SCH_FIN_OFFICER', 'name' => 'นักการเงินและบัญชี', 'description' => 'Finance officer — budget and payment-related steps'],
        ];

        foreach ($positions as $pos) {
            Position::updateOrCreate(
                ['code' => $pos['code']],
                ['name' => $pos['name'], 'description' => $pos['description'], 'is_active' => true]
            );
        }

        $this->command?->info('PositionDemoSeeder: '.count($positions).' school positions.');
    }
}
