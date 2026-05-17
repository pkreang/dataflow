<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('home_dashboard_id')
                ->nullable()
                ->after('dashboard_config')
                ->constrained('report_dashboards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['home_dashboard_id']);
            $table->dropColumn('home_dashboard_id');
        });
    }
};
