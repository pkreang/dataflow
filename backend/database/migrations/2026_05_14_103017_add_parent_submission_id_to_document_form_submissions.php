<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->foreignId('parent_submission_id')
                ->nullable()
                ->after('approval_instance_id')
                ->constrained('document_form_submissions')
                ->nullOnDelete();
            $table->index('parent_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->dropForeign(['parent_submission_id']);
            $table->dropIndex(['parent_submission_id']);
            $table->dropColumn('parent_submission_id');
        });
    }
};
