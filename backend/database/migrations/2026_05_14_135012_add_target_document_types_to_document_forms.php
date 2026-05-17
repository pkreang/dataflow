<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_forms', function (Blueprint $table) {
            $table->json('target_document_types')->nullable()->after('evaluation_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('document_forms', function (Blueprint $table) {
            $table->dropColumn('target_document_types');
        });
    }
};
