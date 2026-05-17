<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicates('approval_instances', 'document_type', 'reference_no');
        $this->assertNoDuplicates('document_form_submissions', 'form_id', 'reference_no');

        Schema::table('approval_instances', function (Blueprint $table) {
            $table->unique(
                ['document_type', 'reference_no'],
                'approval_instances_doctype_refno_unique'
            );
        });

        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->unique(
                ['form_id', 'reference_no'],
                'submissions_form_refno_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->dropUnique('approval_instances_doctype_refno_unique');
        });

        Schema::table('document_form_submissions', function (Blueprint $table) {
            $table->dropUnique('submissions_form_refno_unique');
        });
    }

    private function assertNoDuplicates(string $table, string $scopeColumn, string $refColumn): void
    {
        $duplicates = DB::table($table)
            ->select($scopeColumn, $refColumn, DB::raw('COUNT(*) as c'))
            ->whereNotNull($refColumn)
            ->groupBy($scopeColumn, $refColumn)
            ->having('c', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot add unique index on %s(%s, %s): existing duplicates found: %s',
                $table,
                $scopeColumn,
                $refColumn,
                $duplicates->toJson()
            ));
        }
    }
};
