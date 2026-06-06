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
        Schema::table('document_form_workflow_policies', function (Blueprint $table) {
            $table->string('amount_field_key', 100)->nullable()->after('use_amount_condition');
        });
    }

    public function down(): void
    {
        Schema::table('document_form_workflow_policies', function (Blueprint $table) {
            $table->dropColumn('amount_field_key');
        });
    }
};
