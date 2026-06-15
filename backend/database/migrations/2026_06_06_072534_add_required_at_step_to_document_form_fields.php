<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_form_fields', function (Blueprint $table) {
            $table->json('required_at_step')->nullable()->after('required_rules');
        });
    }

    public function down(): void
    {
        Schema::table('document_form_fields', function (Blueprint $table) {
            $table->dropColumn('required_at_step');
        });
    }
};
