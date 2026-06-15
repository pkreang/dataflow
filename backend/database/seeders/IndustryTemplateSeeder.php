<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * School eForm playbook (default product mode).
 *
 *   php artisan db:seed --class=IndustryTemplateSeeder
 *
 * Factory CMMS templates: run `FactoryCmmsTemplateSeeder` separately if needed.
 */
class IndustryTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Everything school-specific lives here so factory deployments
        // (composer switch:factory) don't end up with school doc types, school
        // demo forms, school KPIs, or school positions.
        //
        // Order matters:
        //   DocumentTypeSeeder      — school doc types (school_leave_request, …)
        //   DocumentFormSeeder      — demo forms reference those doc types
        //   PositionDemoSeeder      — SCH_* positions used by SchoolEFormTemplateSeeder
        //   SchoolEFormTemplateSeeder — workflows + policies for school doc types
        //   HomeDashboardSeeder     — Home (Default) + Home (Manager) dashboards
        //                             whose widgets read school_eforms_pending /
        //                             document_form_submissions; sets
        //                             default_home_dashboard_id. FactoryDashboardSeeder
        //                             overrides that setting for factory installs.
        $this->call([
            DocumentTypeSeeder::class,
            DocumentFormSeeder::class,
            PositionDemoSeeder::class,
            SchoolEFormTemplateSeeder::class,
            LeaveRequestTemplateSeeder::class,
            PurchaseTemplateSeeder::class,
            ExpenseClaimTemplateSeeder::class,
            MemoTemplateSeeder::class,
            ITRequestTemplateSeeder::class,
            QuotationTemplateSeeder::class,
            MeetingRoomTemplateSeeder::class,
            HomeDashboardSeeder::class,
        ]);
    }
}
