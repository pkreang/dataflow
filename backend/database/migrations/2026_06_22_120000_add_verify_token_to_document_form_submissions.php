<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->string('verify_token', 32)->nullable()->unique()->after('reference_no');
        });

        // backfill existing rows so the public verify QR works for old submissions
        foreach (DB::table('document_form_submissions')->whereNull('verify_token')->pluck('id') as $id) {
            DB::table('document_form_submissions')->where('id', $id)
                ->update(['verify_token' => Str::lower(Str::random(12))]);
        }
    }

    public function down(): void
    {
        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->dropUnique(['verify_token']);
            $table->dropColumn('verify_token');
        });
    }
};
