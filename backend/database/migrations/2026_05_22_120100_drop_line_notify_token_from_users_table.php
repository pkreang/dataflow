<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LINE Notify was discontinued 2025-03-31. The legacy per-user token column
 * has no use after migrating to LINE Messaging API + LINE Login. Drop it.
 *
 * Replacement column `users.line_user_id` was added by the prior migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('line_notify_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('line_notify_token', 255)->nullable()->after('phone');
        });
    }
};
