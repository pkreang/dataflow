<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as [$table, $prefix]) {
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
            ['departments',          'DEPT'],
            ['positions',            'POS'],
            ['equipment_categories', 'EQCAT'],
            ['equipment_locations',  'EQLOC'],
        ];
    }
};
