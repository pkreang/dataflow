<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Org-model consolidation Phase 0 — pivot form↔org_unit คู่ขนานกับ document_form_departments
 * (form visibility). ยังไม่ถูกอ่านจนกว่า scopeVisibleToUser จะย้ายไป org_unit (Phase 2b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_form_org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('document_forms')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->unique(['form_id', 'org_unit_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_form_org_units');
    }
};
