<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 cleanup of the home-dashboard rewrite. The legacy hardcoded KPI
 * grid persisted user picks in `users.dashboard_config` (JSON of card keys);
 * the new ReportDashboard-backed home page uses `users.home_dashboard_id`
 * (FK) instead. Card preferences from the old system are intentionally
 * discarded — there's no clean mapping from "card list" to "dashboard pick"
 * and the feature was rarely used in practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'dashboard_config')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dashboard_config');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_config')->nullable()->after('is_super_admin');
        });
    }
};
