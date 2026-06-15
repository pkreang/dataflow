<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->json('approver_rules')->nullable()->after('approver_ref');
        });

        Schema::table('approval_instance_steps', function (Blueprint $table) {
            $table->json('approver_rules')->nullable()->after('approver_ref');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->dropColumn('approver_rules');
        });

        Schema::table('approval_instance_steps', function (Blueprint $table) {
            $table->dropColumn('approver_rules');
        });
    }
};
