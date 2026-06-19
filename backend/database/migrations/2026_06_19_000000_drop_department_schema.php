<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Org-model consolidation Phase 4c — drop the `departments` table and every
 * `department_id` reference now that org_units drives all routing, visibility,
 * and reporting. The `org_unit_id` columns (Phase 0) take over completely.
 *
 * Order matters on MySQL: drop the FKs/indexes anchored on department_id before
 * dropping the column, then drop the pivot/child tables before `departments`.
 */
return new class extends Migration
{
    private const POLICY_TABLE = 'document_form_workflow_policies';

    private const POLICY_OLD_UNIQUE = 'dfwp_form_dept_pos_unique';

    private const POLICY_NEW_UNIQUE = 'dfwp_form_org_pos_unique';

    private const POLICY_DEPT_FK = 'document_form_workflow_policies_department_id_foreign';

    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Policies: swap the dept-anchored unique for an org_unit/position one,
        //    keeping form_id continuously anchored (MySQL FK requirement).
        if (Schema::hasColumn(self::POLICY_TABLE, 'department_id')) {
            // MySQL: drop the dept FK up-front so the index swap and column drop
            // are unobstructed. (On SQLite the FK is dropped together with the
            // column below — SQLite rebuilds the whole table either way.)
            $mysqlFkAlreadyDropped = false;
            if ($isMysql && $this->mysqlFkExists(self::POLICY_DEPT_FK)) {
                Schema::table(self::POLICY_TABLE, function (Blueprint $table) {
                    $table->dropForeign(self::POLICY_DEPT_FK);
                });
                $mysqlFkAlreadyDropped = true;
            }

            if (! $this->indexExists(self::POLICY_TABLE, self::POLICY_NEW_UNIQUE)) {
                Schema::table(self::POLICY_TABLE, function (Blueprint $table) {
                    $table->unique(['form_id', 'org_unit_id', 'position_id'], self::POLICY_NEW_UNIQUE);
                });
            }

            if ($this->indexExists(self::POLICY_TABLE, self::POLICY_OLD_UNIQUE)) {
                Schema::table(self::POLICY_TABLE, function (Blueprint $table) {
                    $table->dropUnique(self::POLICY_OLD_UNIQUE);
                });
            }

            Schema::table(self::POLICY_TABLE, function (Blueprint $table) use ($isMysql, $mysqlFkAlreadyDropped) {
                // SQLite keeps the FK in its table definition until the column is
                // dropped — drop it in the same rebuild or the drop fails with
                // "unknown column department_id in foreign key definition".
                if (! $isMysql || ! $mysqlFkAlreadyDropped) {
                    $table->dropForeign(['department_id']);
                }
                $table->dropColumn('department_id');
            });
        }

        // 2. Remaining core tables: drop FK (both drivers) + column together.
        foreach (['users', 'approval_instances', 'document_form_submissions'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'department_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        // 3. Drop department_id from every dedicated fdata_* submission table.
        foreach ($this->fdataTables() as $tableName) {
            if (Schema::hasColumn($tableName, 'department_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('department_id');
                });
            }
        }

        // 4. Drop pivot / child tables (FK to departments) first, then departments
        //    itself (which also removes the Phase 0 bridge column on it).
        Schema::dropIfExists('document_form_departments');
        Schema::dropIfExists('department_workflow_bindings');
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        // Best-effort rollback — recreates the schema (no FK constraints, data not
        // restored). The branch ships via migrate:fresh, so this only guards against
        // a fatal on an accidental rollback.
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('auto_code')->nullable();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('org_unit_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('department_workflow_bindings')) {
            Schema::create('department_workflow_bindings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('document_type');
                $table->unsignedBigInteger('workflow_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_form_departments')) {
            Schema::create('document_form_departments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('department_id');
                $table->timestamps();
            });
        }

        foreach (['users', 'approval_instances', 'document_form_submissions', self::POLICY_TABLE] as $tableName) {
            if (! Schema::hasColumn($tableName, 'department_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('department_id')->nullable();
                });
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function fdataTables(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return array_map(
                fn ($r) => $r->name,
                DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'fdata_%'")
            );
        }

        return array_map(
            fn ($r) => array_values((array) $r)[0],
            DB::select("SHOW TABLES LIKE 'fdata\\_%'")
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($idx) => $idx['name'] === $indexName);
    }

    private function mysqlFkExists(string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
               AND CONSTRAINT_NAME = ?',
            [$name]
        )->cnt;
    }
};
