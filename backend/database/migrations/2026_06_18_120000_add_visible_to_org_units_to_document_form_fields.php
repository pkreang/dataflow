<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Org-model consolidation Phase 2c — field-level visibility ตาม org_unit คู่ขนานกับ
 * visible_to_departments (additive). reader อ่าน org ก่อน dept fallback. ดู spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_form_fields', function (Blueprint $table) {
            $table->json('visible_to_org_units')->nullable()->after('visible_to_departments');
        });
    }

    public function down(): void
    {
        Schema::table('document_form_fields', function (Blueprint $table) {
            $table->dropColumn('visible_to_org_units');
        });
    }
};
