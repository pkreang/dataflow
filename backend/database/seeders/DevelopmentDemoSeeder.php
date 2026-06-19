<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Optional demo / pilot dataset (not run from DatabaseSeeder).
 *
 * Org units: SCH_* only (SchoolEFormTemplateSeeder). No CMMS equipment/spare-parts demo.
 *
 *   php artisan db:seed --class=DevelopmentDemoSeeder
 */
class DevelopmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PositionDemoSeeder::class,
            IndustryTemplateSeeder::class,
            DashboardSeeder::class,
            DemoPeopleSeeder::class,
            OrgUnitSeeder::class,
        ]);
    }
}
