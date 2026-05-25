<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (target person, evaluator, role) within a cycle. `submission_id`
 * is set when the cycle is opened — it points to the draft submission owned
 * by the evaluator. The `role` column is a label ('self' / 'supervisor' /
 * 'peer') so reporting (Phase 3) can group by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_cycle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('kpi_cycles')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('evaluator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 32)->default('supervisor');
            $table->foreignId('submission_id')->nullable()
                ->constrained('document_form_submissions')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['cycle_id', 'target_user_id', 'evaluator_user_id', 'role'],
                'kpi_cycle_assignments_unique'
            );
            $table->index('evaluator_user_id');
            $table->index('target_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_cycle_assignments');
    }
};
