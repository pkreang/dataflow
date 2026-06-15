<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `returned` status to approval_instances — used when an approver
 * sends a request back to the requester (the instance is closed; the
 * submission goes back to draft for editing + resubmission).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'returned'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }
};
