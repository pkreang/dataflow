<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->unsignedSmallInteger('escalation_after_days')->nullable()->after('allow_requester_override');
        });

        Schema::table('approval_instance_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('escalation_after_days')->nullable()->after('require_signature');
            $table->timestamp('escalation_notified_at')->nullable()->after('escalation_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->dropColumn('escalation_after_days');
        });

        Schema::table('approval_instance_steps', function (Blueprint $table) {
            $table->dropColumn(['escalation_after_days', 'escalation_notified_at']);
        });
    }
};
