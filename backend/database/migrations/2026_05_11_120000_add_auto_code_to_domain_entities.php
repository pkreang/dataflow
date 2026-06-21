<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the auto_code pattern (see 2026_05_10_120000) from the 4 original
 * master entities to every remaining domain entity in the system. Each row
 * gets a system-generated PREFIX-NNN identifier so it's referenceable in
 * audits, paperwork, and verbal handoffs without leaning on the numeric `id`.
 *
 * Out of scope (intentional): transaction documents that already use
 * reference_no via running_number_configs, Spatie permission/role tables,
 * child/inline rows, logs/audit tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as [$table, $prefix]) {
            if (! Schema::hasTable($table)) {
                continue; // table removed by a later product change (e.g. CMMS teardown)
            }
            Schema::table($table, function (Blueprint $t) {
                $t->string('auto_code', 20)->nullable()->after('id');
            });

            $i = 1;
            DB::table($table)->orderBy('id')->cursor()->each(function ($row) use ($table, $prefix, &$i) {
                DB::table($table)->where('id', $row->id)->update([
                    'auto_code' => $prefix.'-'.str_pad((string) $i++, 3, '0', STR_PAD_LEFT),
                ]);
            });

            Schema::table($table, function (Blueprint $t) {
                $t->unique('auto_code');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as [$table, $_prefix]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_auto_code_unique');
                $t->dropColumn('auto_code');
            });
        }
    }

    /** @return list<array{0:string,1:string}> */
    private function tables(): array
    {
        return [
            ['users',                   'USER'],
            ['companies',               'COMP'],
            ['branches',                'BR'],
            ['document_types',          'DOCTYPE'],
            ['document_forms',          'FORM'],
            ['equipment',               'EQ'],
            ['spare_parts',             'SP'],
            ['lookup_lists',            'LKLIST'],
            ['approval_workflows',      'WF'],
            ['running_number_configs',  'RNC'],
            ['report_dashboards',       'DASH'],
            ['navigation_menus',        'NAV'],
        ];
    }
};
