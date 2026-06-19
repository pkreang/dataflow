<?php

namespace App\Services;

use App\Models\DocumentFormColumnAnnotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SchemaFirstFormService
{
    /** Columns the form UI should never touch. */
    public const RESERVED_COLUMNS = [
        'id', 'user_id', 'org_unit_id', 'status',
        'reference_no', 'approval_instance_id',
        'created_at', 'updated_at',
    ];

    /**
     * Read DB schema for the table.
     *
     * @return array<int, array{name:string,type_name:string,type:string,nullable:bool,default:mixed,enum_options:?array<int,string>,is_reserved:bool,default_ui_type:string}>
     */
    public function introspect(string $tableName): array
    {
        if (! Schema::hasTable($tableName)) {
            return [];
        }

        $columns = Schema::getColumns($tableName);

        return array_map(function (array $c) {
            $enumOptions = $this->parseEnumOptions($c['type'] ?? '');

            return [
                'name' => $c['name'],
                'type_name' => $c['type_name'] ?? 'string',
                'type' => $c['type'] ?? '',
                'nullable' => (bool) ($c['nullable'] ?? true),
                'default' => $c['default'] ?? null,
                'enum_options' => $enumOptions,
                'is_reserved' => in_array($c['name'], self::RESERVED_COLUMNS, true),
                'default_ui_type' => $this->guessUiType($c),
            ];
        }, $columns);
    }

    /**
     * Seed annotations for every column in the table. Idempotent — existing rows
     * keep their label / sort_order / ui_type overrides; only missing rows are
     * created.
     */
    public function bootstrap(string $tableName): int
    {
        $introspected = $this->introspect($tableName);
        $created = 0;

        foreach ($introspected as $idx => $col) {
            $annotation = DocumentFormColumnAnnotation::firstOrCreate(
                [
                    'table_name' => $tableName,
                    'column_name' => $col['name'],
                ],
                [
                    'label_en' => $col['name'],
                    'label_th' => null,
                    'ui_type' => $col['default_ui_type'],
                    'sort_order' => $idx + 1,
                    'col_span' => 0,
                    'is_visible' => ! $col['is_reserved'],
                    'is_required' => ! $col['nullable'] && ! $col['is_reserved'] && $col['default'] === null,
                    'options' => $col['enum_options'] ?: null,
                ]
            );

            if ($annotation->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Merge annotations + introspection into a form definition. Returns only
     * visible fields ordered by sort_order.
     *
     * @return Collection<int, object>
     */
    public function getFormDefinition(string $tableName): Collection
    {
        $introspected = collect($this->introspect($tableName))->keyBy('name');
        $annotations = DocumentFormColumnAnnotation::query()
            ->where('table_name', $tableName)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        return $annotations->map(function (DocumentFormColumnAnnotation $ann) use ($introspected) {
            $db = $introspected->get($ann->column_name);

            // Field object shaped like DocumentFormField so existing dynamic-field
            // component can consume it.
            return (object) [
                'field_key' => $ann->column_name,
                'label' => $ann->label(),
                'field_type' => $ann->ui_type,
                'is_required' => $ann->is_required || ($db && ! $db['nullable'] && ! $db['is_reserved']),
                'col_span' => $ann->col_span,
                'placeholder' => $ann->placeholder,
                'options' => $ann->options ?: ($db['enum_options'] ?? null),
                'visibility_rules' => $ann->visibility_rules,
                'validation_rules' => $ann->validation_rules,

                // Flags used by dynamic-field component (must be array, not null)
                'effective_editable_by' => ['requester'],
            ];
        });
    }

    /**
     * Build Laravel validation rules array for the form.
     *
     * @return array<string, array<int, string>>
     */
    public function validationRules(string $tableName): array
    {
        $rules = [];
        $def = $this->getFormDefinition($tableName);

        foreach ($def as $field) {
            $r = $field->is_required ? ['required'] : ['nullable'];

            // Rules array — pipe-joined strings are NOT split inside arrays.
            $r = array_merge($r, match ($field->field_type) {
                'number', 'currency' => ['numeric'],
                'date' => ['date'],
                'datetime' => ['date'],
                'time' => ['string'],
                'email' => ['email'],
                'checkbox', 'multi_select' => ['array'],
                'image' => ['file', 'image', 'max:5120'],
                'file' => ['file'],
                default => ['string'],
            });

            $rules[$field->field_key] = $r;
        }

        return $rules;
    }

    private function guessUiType(array $column): string
    {
        $name = $column['name'];
        $typeName = $column['type_name'] ?? '';

        return match (true) {
            // Name-based hints take precedence — `reporter_signature` (TEXT) should
            // render as signature, not textarea.
            str_contains($name, 'signature') => 'signature',
            str_contains($name, 'photo'), str_contains($name, 'image') => 'image',
            str_contains($name, 'email') => 'email',
            str_contains($name, 'phone') => 'phone',
            // DB type-based fallback.
            $typeName === 'enum' => 'select',
            $typeName === 'date' => 'date',
            $typeName === 'time' => 'time',
            $typeName === 'timestamp', $typeName === 'datetime' => 'datetime',
            $typeName === 'text' => 'textarea',
            $typeName === 'decimal' => 'number',
            $typeName === 'bigint', $typeName === 'integer', $typeName === 'int' => 'number',
            $typeName === 'json' => 'multi_select',
            default => 'text',
        };
    }

    /**
     * Parse `enum('a','b','c')` → ['a', 'b', 'c']
     *
     * @return array<int,string>|null
     */
    private function parseEnumOptions(string $type): ?array
    {
        if (! preg_match('/^enum\\((.+)\\)$/i', $type, $m)) {
            return null;
        }
        $raw = $m[1];
        $parts = [];
        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $raw, $matches)) {
            foreach ($matches[1] as $v) {
                $parts[] = stripslashes($v);
            }
        }

        return $parts ?: null;
    }
}
