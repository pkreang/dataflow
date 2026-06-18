<?php

namespace App\Services;

use App\Models\DocumentForm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormSchemaService
{
    /** Standard columns present in every fdata_* table (never touched by syncTable). */
    public const RESERVED_COLUMNS = [
        'id', 'user_id', 'department_id', 'org_unit_id', 'status',
        'reference_no', 'approval_instance_id',
        'created_at', 'updated_at',
    ];

    /** Field types that do not store data — skip when creating columns. */
    public const SKIP_TYPES = ['section', 'auto_number', 'page_break', 'qr_code'];

    public function getTableName(string $formKey): string
    {
        return 'fdata_'.$formKey;
    }

    /**
     * Create the dedicated submission table for a form.
     * Idempotent — skips creation if table already exists.
     */
    public function createTable(DocumentForm $form): void
    {
        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $bp) use ($form) {
            $bp->id();
            $bp->unsignedBigInteger('user_id')->nullable()->index();
            $bp->unsignedBigInteger('department_id')->nullable()->index();
            $bp->unsignedBigInteger('org_unit_id')->nullable()->index();
            $bp->enum('status', ['draft', 'submitted'])->default('draft')->index();
            $bp->string('reference_no')->nullable();
            $bp->unsignedBigInteger('approval_instance_id')->nullable()->index();
            $bp->timestamps();

            foreach ($form->fields as $field) {
                if (in_array($field->field_type, self::SKIP_TYPES, true)) {
                    continue;
                }
                $this->addColumn($bp, $field->field_key, $field->field_type);
            }
        });
    }

    /**
     * Sync table columns to match current field definitions.
     * Adds new columns, drops removed ones. Never touches reserved columns.
     *
     * @param  Collection<int, \App\Models\DocumentFormField>  $newFields
     */
    public function syncTable(DocumentForm $form, Collection $newFields): void
    {
        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (! Schema::hasTable($table)) {
            $this->createTable($form);

            return;
        }

        $existingColumns = collect(Schema::getColumnListing($table));
        $reserved = collect(self::RESERVED_COLUMNS);

        // Desired dynamic columns from current fields
        $desiredColumns = $newFields
            ->filter(fn ($f) => ! in_array($f->field_type, self::SKIP_TYPES, true))
            ->pluck('field_type', 'field_key');

        // Add columns that don't exist yet
        $toAdd = $desiredColumns->keys()->diff($existingColumns)->values();

        // Drop columns that are no longer in the field list (and not reserved)
        $toDrop = $existingColumns
            ->diff($reserved)
            ->diff($desiredColumns->keys())
            ->values();

        if ($toAdd->isNotEmpty()) {
            Schema::table($table, function (Blueprint $bp) use ($toAdd, $desiredColumns) {
                foreach ($toAdd as $fieldKey) {
                    $this->addColumn($bp, $fieldKey, $desiredColumns[$fieldKey]);
                }
            });
        }

        if ($toDrop->isNotEmpty()) {
            Schema::table($table, function (Blueprint $bp) use ($toDrop) {
                $bp->dropColumn($toDrop->all());
            });
        }
    }

    /**
     * Drop the dedicated submission table.
     */
    public function dropTable(DocumentForm $form): void
    {
        if ($form->submission_table) {
            Schema::dropIfExists($form->submission_table);
        }
    }

    // ── Row CRUD (dual-write support) ──────────────────────────

    /**
     * Ensure the dedicated table exists, creating it lazily if needed.
     */
    public function ensureTableExists(DocumentForm $form): void
    {
        if (! $form->hasDedicatedTable()) {
            return;
        }

        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (! Schema::hasTable($table)) {
            $this->createTable($form->load('fields'));
        }
    }

    /**
     * Insert a row into the fdata_* table. Returns the inserted row ID, or null if no table.
     *
     * @param  array<string, mixed>  $fieldPayload  Field key → value pairs from the form submission
     * @param  array<string, mixed>  $meta           Reserved column values (user_id, department_id, status, etc.)
     */
    public function insertRow(DocumentForm $form, array $fieldPayload, array $meta = []): ?int
    {
        if (! $form->hasDedicatedTable()) {
            return null;
        }

        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (! Schema::hasTable($table)) {
            return null;
        }

        $columns = collect(Schema::getColumnListing($table));
        $row = $this->buildRow($columns, $fieldPayload, $meta);
        $row['created_at'] = now();
        $row['updated_at'] = now();

        return DB::table($table)->insertGetId($row);
    }

    /**
     * Update an existing row in the fdata_* table.
     *
     * @param  array<string, mixed>  $fieldPayload  Field key → value pairs
     * @param  array<string, mixed>  $meta           Reserved column overrides
     */
    public function updateRow(DocumentForm $form, int $rowId, array $fieldPayload, array $meta = []): void
    {
        if (! $form->hasDedicatedTable()) {
            return;
        }

        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = collect(Schema::getColumnListing($table));
        $row = $this->buildRow($columns, $fieldPayload, $meta);
        $row['updated_at'] = now();

        DB::table($table)->where('id', $rowId)->update($row);
    }

    /**
     * Delete a row from the fdata_* table.
     */
    public function deleteRow(DocumentForm $form, int $rowId): void
    {
        if (! $form->hasDedicatedTable()) {
            return;
        }

        $table = $form->submission_table ?: $this->getTableName($form->form_key);

        if (Schema::hasTable($table)) {
            DB::table($table)->where('id', $rowId)->delete();
        }
    }

    /**
     * Build a row array from field payload + meta, filtering to only columns that exist in the table.
     * JSON/array values are encoded automatically.
     */
    private function buildRow(Collection $columns, array $fieldPayload, array $meta): array
    {
        $row = [];

        // Meta columns (reserved)
        foreach ($meta as $key => $value) {
            if ($columns->contains($key) && $key !== 'id') {
                $row[$key] = $value;
            }
        }

        // Field columns
        foreach ($fieldPayload as $key => $value) {
            if ($columns->contains($key)) {
                $row[$key] = is_array($value) ? json_encode($value) : $value;
            }
        }

        return $row;
    }

    // ── Schema management ───────────────────────────────────

    private function addColumn(Blueprint $table, string $fieldKey, string $fieldType): void
    {
        match ($fieldType) {
            'textarea', 'signature' => $table->text($fieldKey)->nullable(),
            'number', 'formula' => $table->decimal($fieldKey, 15, 4)->nullable(),
            'currency' => $table->decimal($fieldKey, 15, 2)->nullable(),
            'date' => $table->date($fieldKey)->nullable(),
            'time' => $table->time($fieldKey)->nullable(),
            'datetime' => $table->dateTime($fieldKey)->nullable(),
            'checkbox', 'table', 'multi_select', 'multi_file', 'group' => $table->json($fieldKey)->nullable(),
            // page_break + qr_code are display-only — no column is created.
            'page_break', 'qr_code' => null,
            default => $table->string($fieldKey)->nullable(),
            // text, email, phone, select, radio, lookup, file, image → string
        };
    }
}
