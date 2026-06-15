<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Submit on behalf of": user_id stays the document OWNER (the person the
     * document is for); created_by_user_id records who actually filed it.
     * NULL = self-filed (the overwhelmingly common case).
     */
    public function up(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->dropColumn('created_by_user_id');
        });
    }
};
