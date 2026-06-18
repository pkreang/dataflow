<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Org-model consolidation Phase 0 — binding org_unit↔workflow ต่อ document_type คู่ขนานกับ
 * department_workflow_bindings. ยังไม่ถูกอ่านจนกว่า resolveWorkflowId จะย้ายไป org_unit (Phase 2a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_unit_workflow_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->string('document_type');
            $table->foreignId('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['org_unit_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_unit_workflow_bindings');
    }
};
