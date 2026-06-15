<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->boolean('allow_requester_override')->default(false)->after('require_signature');
        });

        // Rename setting key
        DB::table('settings')
            ->where('key', 'approval.allow_requester_pick')
            ->update(['key' => 'approval.allow_requester_override']);

        // Convert legacy requester_pick stages: mark override-enabled, type=user.
        // approver_ref stays empty — admin must fix in workflow editor.
        DB::table('approval_workflow_stages')
            ->where('approver_type', 'requester_pick')
            ->update([
                'approver_type' => 'user',
                'allow_requester_override' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('approval_workflow_stages', function (Blueprint $table) {
            $table->dropColumn('allow_requester_override');
        });

        DB::table('settings')
            ->where('key', 'approval.allow_requester_override')
            ->update(['key' => 'approval.allow_requester_pick']);
    }
};
