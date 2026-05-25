<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A KPI cycle bundles a set of evaluations against one DocumentForm during a
 * defined period. Admin creates a cycle in `draft`, adds assignments, then
 * opens it (which spawns draft DocumentFormSubmissions for each evaluator)
 * and closes it when the period ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('form_id')->constrained('document_forms')->restrictOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_cycles');
    }
};
