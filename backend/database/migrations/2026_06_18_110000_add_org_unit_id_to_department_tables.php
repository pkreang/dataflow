<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Org-model consolidation Phase 0 (additive) — เพิ่ม org_unit_id คู่ขนานกับ department_id
 * ในตารางหลัก + bridge บน departments. ยังไม่มีโค้ดอ่าน org_unit_id → ไม่เปลี่ยนพฤติกรรม.
 * ดู doc/org-model-consolidation-spec.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('department_id')
                ->constrained('org_units')->nullOnDelete();
        });

        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('department_id')
                ->constrained('org_units')->nullOnDelete();
        });

        Schema::table('document_form_workflow_policies', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('department_id')
                ->constrained('org_units')->nullOnDelete();
        });

        // bridge: map แต่ละ department → org_unit (ใช้ backfill ข้อมูลเก่า)
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('id')
                ->constrained('org_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });
        Schema::table('document_form_workflow_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });
    }
};
