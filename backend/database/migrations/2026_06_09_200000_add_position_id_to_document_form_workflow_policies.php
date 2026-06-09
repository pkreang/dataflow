<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'document_form_workflow_policies';
    private const OLD_UNIQUE = 'document_form_workflow_policies_form_id_department_id_unique';
    private const NEW_UNIQUE = 'dfwp_form_dept_pos_unique';
    private const DEPT_FK = 'document_form_workflow_policies_department_id_foreign';

    public function up(): void
    {
        // 1. Add position_id column + FK (idempotent)
        if (! Schema::hasColumn(self::TABLE, 'position_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreignId('position_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('positions')
                    ->nullOnDelete();
            });
        }

        // 2. MySQL only: drop department FK before touching the unique index it anchors
        if (DB::getDriverName() === 'mysql' && $this->mysqlFkExists(self::DEPT_FK)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign(self::DEPT_FK);
            });
        }

        // 3. Add new unique first (so form_id FK still has an anchor in MySQL)
        if (! $this->schemaIndexExists(self::NEW_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['form_id', 'department_id', 'position_id'], self::NEW_UNIQUE);
            });
        }

        // 4. Drop old unique
        if ($this->schemaIndexExists(self::OLD_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        // 5. MySQL only: restore department FK
        if (DB::getDriverName() === 'mysql' && ! $this->mysqlFkExists(self::DEPT_FK)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && $this->mysqlFkExists(self::DEPT_FK)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign(self::DEPT_FK);
            });
        }

        if (! $this->schemaIndexExists(self::OLD_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['form_id', 'department_id'], self::OLD_UNIQUE);
            });
        }
        if ($this->schemaIndexExists(self::NEW_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        if (DB::getDriverName() === 'mysql' && ! $this->mysqlFkExists(self::DEPT_FK)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            });
        }

        if (Schema::hasColumn(self::TABLE, 'position_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign(['position_id']);
                $table->dropColumn('position_id');
            });
        }
    }

    private function mysqlFkExists(string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
               AND CONSTRAINT_NAME = ?',
            [self::TABLE, $name]
        )->cnt;
    }

    private function schemaIndexExists(string $indexName): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(fn ($idx) => $idx['name'] === $indexName);
    }
};
