<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shift master + per-user shift assignment.
     * end_time < start_time means the shift crosses midnight (night shift).
     * work_days = JSON array of ISO weekday numbers (1=Mon .. 7=Sun).
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->string('color', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->json('work_days')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shift_schedules');
        Schema::dropIfExists('shifts');
    }
};
