<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\OrgUnit;
use App\Models\RunningNumberConfig;
use App\Models\User;
use App\Services\FormSchemaService;
use App\Support\LookupRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentFormController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly FormSchemaService $schemaService) {}

    private const TABLE_COLUMN_TYPES = ['text', 'number', 'select', 'checkbox', 'date', 'lookup'];

    private const MAX_TABLE_COLUMNS = 40;

    /**
     * Field types exposed in the form builder UI (no legacy *_lookup types).
     *
     * @return list<string>
     */
    private static function allowedFieldTypes(): array
    {
        return [
            'text', 'textarea', 'number', 'date', 'select', 'checkbox', 'radio', 'file', 'multi_file',
            'time', 'datetime', 'email', 'phone', 'signature', 'currency', 'lookup', 'table', 'section',
            'auto_number', 'image', 'multi_select', 'group', 'page_break', 'qr_code', 'formula',
        ];
    }

    /**
     * Field types that may appear inside a `group` (subform) field's inner
     * fields list. Excludes uploads/signature/nested groups/structural items
     * because they break the simple per-row binding pattern.
     *
     * @var list<string>
     */
    public const GROUP_INNER_FIELD_TYPES = [
        'text', 'textarea', 'number', 'currency', 'date', 'time', 'datetime',
        'email', 'phone', 'select', 'multi_select', 'radio', 'checkbox', 'lookup',
    ];

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'document_forms_per_page');
        $search = trim((string) $request->input('search', ''));

        $query = DocumentForm::query()
            ->withCount('fields')
            ->with(['workflowPolicies.workflow', 'workflowPolicies.ranges'])
            ->orderBy('name');

        if ($search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('form_key', 'like', $needle)
                    ->orWhere('document_type', 'like', $needle);
            });
        }

        $forms = $query->paginate($perPage)->withQueryString();
        $totalForms = $forms->total();

        return view('settings.document-forms.index', compact('forms', 'perPage', 'search', 'totalForms'));
    }

    public function create(Request $request): View
    {
        $lookupSources = LookupRegistry::sources();
        $workflowStepsByDocType = $this->workflowStepsByDocType();
        $orgUnits = OrgUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $companyUsers = $this->companyUsersForPicker();
        $runningNumberConfigs = $this->runningNumberConfigsForBuilder();
        $preset = ['document_type' => $request->query('document_type')];
        $allowedOrgUnitIds = [];

        return view('settings.document-forms.create', compact('lookupSources', 'workflowStepsByDocType', 'orgUnits', 'companyUsers', 'runningNumberConfigs', 'preset', 'allowedOrgUnitIds'));
    }

    public function edit(DocumentForm $documentForm): View
    {
        $documentForm->load(['fields', 'orgUnits']);
        $lookupSources = LookupRegistry::sources();
        $workflowStepsByDocType = $this->workflowStepsByDocType();
        $orgUnits = OrgUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $companyUsers = $this->companyUsersForPicker();
        $runningNumberConfigs = $this->runningNumberConfigsForBuilder();
        $allowedOrgUnitIds = $documentForm->orgUnits->pluck('id')->all();

        return view('settings.document-forms.edit', compact('documentForm', 'lookupSources', 'workflowStepsByDocType', 'orgUnits', 'companyUsers', 'runningNumberConfigs', 'allowedOrgUnitIds'));
    }

    /**
     * Map of `document_type` → running-number config snapshot used by the form
     * builder to surface (a) the live next-number preview beside the document
     * type picker and (b) a per-field preview next to any `auto_number` field.
     * Returning a flat array keyed by document_type lets the Alpine state look
     * up `runningNumberConfigs[currentDocumentType]` in O(1) on every change.
     *
     * @return array<string, array{prefix:string,digit_count:int,include_year:bool,include_month:bool,reset_mode:string,last_number:int,is_active:bool,preview:string}>
     */
    private function runningNumberConfigsForBuilder(): array
    {
        $now = now();
        $out = [];

        foreach (\App\Models\RunningNumberConfig::all() as $config) {
            $preview = $config->prefix
                .($config->include_year ? $now->format('Y') : '')
                .($config->include_month ? $now->format('m') : '')
                .'-'.str_pad((string) ($config->last_number + 1), $config->digit_count, '0', STR_PAD_LEFT);

            $out[$config->document_type] = [
                'prefix' => (string) $config->prefix,
                'digit_count' => (int) $config->digit_count,
                'include_year' => (bool) $config->include_year,
                'include_month' => (bool) $config->include_month,
                'reset_mode' => (string) $config->reset_mode,
                'last_number' => (int) $config->last_number,
                'is_active' => (bool) $config->is_active,
                'preview' => $preview,
            ];
        }

        return $out;
    }

    /**
     * Active users available as field-level editors. Returned id+name pairs are
     * consumed by the form builder JS to render the per-field user picker.
     * Sorted by name for stable lookup ordering.
     *
     * @return list<array{id:int,name:string}>
     */
    private function companyUsersForPicker(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => $u->full_name])
            ->all();
    }

    /**
     * Map each document_type to the step labels its workflows expose. Admins use
     * this list to decide which roles can edit each field (`editable_by`).
     *
     * Returns: ['maintenance_request' => [['step_no' => 1, 'name' => 'Supervisor'], ...], ...]
     * When multiple workflows exist for one document_type, step names are taken
     * from the first workflow that defines that step_no (admin sees one label per step).
     *
     * @return array<string, list<array{step_no:int,name:string}>>
     */
    private function workflowStepsByDocType(): array
    {
        $rows = DB::table('approval_workflow_stages')
            ->join('approval_workflows', 'approval_workflow_stages.workflow_id', '=', 'approval_workflows.id')
            ->where('approval_workflow_stages.is_active', true)
            ->select(
                'approval_workflows.document_type',
                'approval_workflow_stages.step_no',
                'approval_workflow_stages.name'
            )
            ->orderBy('approval_workflow_stages.step_no')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $bucket = &$result[$row->document_type];
            $bucket ??= [];
            if (! array_key_exists((int) $row->step_no, $bucket)) {
                $bucket[(int) $row->step_no] = [
                    'step_no' => (int) $row->step_no,
                    'name' => (string) $row->name,
                ];
            }
        }
        foreach ($result as $docType => $byStep) {
            ksort($byStep);
            $result[$docType] = array_values($byStep);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Prefix a field-level validation error with the field's 1-based position
     * + display label/key so users editing complex forms (20+ fields) can
     * find the offending row instead of scanning identical messages.
     */
    private function formatFieldError(int $index, array $field, string $messageKey): string
    {
        $position = $index + 1;
        $label = trim((string) ($field['label_th'] ?? $field['label'] ?? $field['label_en'] ?? ''));
        $key = trim((string) ($field['field_key'] ?? ''));
        $name = $label !== '' ? $label : ($key !== '' ? $key : (string) __('common.document_form_field_untitled'));

        return (string) __('common.document_form_field_error_prefix', ['n' => $position, 'name' => $name])
            .' '.(string) __($messageKey);
    }

    private function validatedDocumentFormPayload(Request $request, ?DocumentForm $existing = null): array
    {
        $sourceKeys = LookupRegistry::sourceKeys();

        $formKeyRule = Rule::unique('document_forms', 'form_key');
        if ($existing !== null) {
            $formKeyRule = $formKeyRule->ignore($existing->id);
        }

        $validator = Validator::make($request->all(), [
            'form_key' => ['required', 'string', 'max:100', 'alpha_dash', $formKeyRule],
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:50', Rule::exists('document_types', 'code')->where('is_active', true)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'evaluation_enabled' => ['nullable', 'boolean'],
            'target_document_types' => ['nullable', 'array'],
            'target_document_types.*' => ['string', 'max:50'],
            'layout_columns' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'allowed_org_units' => ['nullable', 'array'],
            'allowed_org_units.*' => ['integer', 'exists:org_units,id'],
            'table_name' => [
                'required', 'string', 'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('document_forms', 'submission_table')->ignore($existing?->id),
            ],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.field_key' => ['required', 'string', 'max:100', 'alpha_dash'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.label_en' => ['nullable', 'string', 'max:255'],
            'fields.*.label_th' => ['nullable', 'string', 'max:255'],
            'fields.*.field_type' => ['required', Rule::in(self::allowedFieldTypes())],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.is_searchable' => ['nullable', 'boolean'],
            'fields.*.is_readonly' => ['nullable', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.default_value' => ['nullable', 'string', 'max:255'],
            'fields.*.options_raw' => ['nullable', 'string'],
            'fields.*.lookup_source' => ['nullable', 'string', 'max:100'],
            'fields.*.depends_on' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'fields.*.foreign_key' => ['nullable', 'string', 'max:100'],
            'fields.*.table_columns' => ['nullable', 'string', 'max:65535'],
            'fields.*.group_options' => ['nullable', 'string', 'max:65535'],
            'fields.*.qr_options' => ['nullable', 'string', 'max:2000'],
            'fields.*.expression' => ['nullable', 'string', 'max:500'],
            'fields.*.decimals' => ['nullable', 'integer', 'min:0', 'max:8'],
            'fields.*.col_span' => ['nullable', 'integer', 'min:0', 'max:4'],
            'fields.*.visibility_rules' => ['nullable', 'string', 'max:65535'],
            'fields.*.required_rules' => ['nullable', 'string', 'max:65535'],
            'fields.*.required_at_step' => ['nullable', 'string', 'max:500'],
            'fields.*.validation_rules' => ['nullable', 'string', 'max:65535'],
            'fields.*.editable_by' => ['nullable', 'string', 'max:2000'],
            'fields.*.visible_to_org_units' => ['nullable', 'string', 'max:2000'],
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request, $sourceKeys, $existing): void {
            $fields = $request->input('fields');
            if (! is_array($fields)) {
                return;
            }

            $seenKeys = [];
            foreach ($fields as $i => $field) {
                if (! is_array($field)) {
                    continue;
                }
                $key = isset($field['field_key']) ? (string) $field['field_key'] : '';
                if ($key === '') {
                    continue;
                }
                if (isset($seenKeys[$key])) {
                    $v->errors()->add("fields.{$i}.field_key", $this->formatFieldError($i, $field, 'validation.document_form.duplicate_field_key'));
                    $prev = $seenKeys[$key];
                    $v->errors()->add("fields.{$prev}.field_key", $this->formatFieldError($prev, $fields[$prev], 'validation.document_form.duplicate_field_key'));

                    continue;
                }
                // Reserved keys collide with system columns IF the field type creates a real
                // DB column. SKIP_TYPES (section/auto_number/page_break/qr_code) don't add
                // columns, so they're allowed to use reserved keys like reference_no — this
                // is in fact how auto_number is wired to show the system reference_no value.
                $fieldType = $field['field_type'] ?? '';
                if (in_array($key, FormSchemaService::RESERVED_COLUMNS, true)
                    && ! in_array($fieldType, FormSchemaService::SKIP_TYPES, true)) {
                    $v->errors()->add("fields.{$i}.field_key", $this->formatFieldError($i, $field, 'validation.document_form.field_key_reserved'));

                    continue;
                }
                $seenKeys[$key] = $i;
            }

            $tableName = (string) ($request->input('table_name') ?? '');
            if ($tableName !== '' && Schema::hasTable($tableName)) {
                $ownedByOther = DocumentForm::where('submission_table', $tableName)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->exists();
                $ownedByThis = $existing && $existing->submission_table === $tableName;
                if (! $ownedByOther && ! $ownedByThis) {
                    $v->errors()->add('table_name', __('validation.document_form.table_name_conflicts_system'));
                }
            }

            foreach ($fields as $i => $field) {
                if (! is_array($field)) {
                    continue;
                }
                $type = $field['field_type'] ?? '';

                if ($type === 'lookup') {
                    if (empty($field['lookup_source']) || ! in_array($field['lookup_source'], $sourceKeys, true)) {
                        $v->errors()->add("fields.{$i}.lookup_source", $this->formatFieldError($i, $field, 'validation.document_form.lookup_source_required'));
                    }
                    if (! empty($field['depends_on'])) {
                        $parentKey = (string) $field['depends_on'];
                        $parent = null;
                        foreach ($fields as $pi => $f) {
                            if ($pi === $i || ! is_array($f)) {
                                continue;
                            }
                            if (($f['field_key'] ?? null) === $parentKey) {
                                $parent = $f;
                                break;
                            }
                        }
                        if (! is_array($parent) || ($parent['field_type'] ?? '') !== 'lookup') {
                            $v->errors()->add("fields.{$i}.depends_on", $this->formatFieldError($i, $field, 'validation.document_form.depends_on_invalid'));
                        }
                        if (empty($field['foreign_key'])) {
                            $v->errors()->add("fields.{$i}.foreign_key", $this->formatFieldError($i, $field, 'validation.document_form.foreign_key_required'));
                        } elseif (! preg_match('/^[a-z_]+$/', (string) $field['foreign_key'])) {
                            $v->errors()->add("fields.{$i}.foreign_key", $this->formatFieldError($i, $field, 'validation.document_form.foreign_key_invalid'));
                        }
                    }
                }

                if (in_array($type, ['select', 'radio', 'checkbox', 'multi_select'], true)) {
                    // multi_select accepts lookup_source as an alternative to hardcoded options_raw
                    if ($type === 'multi_select' && ! empty($field['lookup_source'])) {
                        // lookup source set → skip the options_raw check
                    } else {
                        $raw = $field['options_raw'] ?? '';
                        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));
                        if (count($lines) < 1) {
                            $v->errors()->add("fields.{$i}.options_raw", $this->formatFieldError($i, $field, 'validation.document_form.options_required'));
                        }
                    }
                }

                if ($type === 'table') {
                    $raw = $field['table_columns'] ?? '';
                    if ($raw === '' || $raw === null) {
                        $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_columns_required'));

                        continue;
                    }
                    $decoded = json_decode((string) $raw, true);
                    if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                        $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_columns_invalid_json'));

                        continue;
                    }
                    if (count($decoded) < 1) {
                        $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_columns_required'));

                        continue;
                    }
                    if (count($decoded) > self::MAX_TABLE_COLUMNS) {
                        $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_columns_too_many'));

                        continue;
                    }
                    $colKeys = [];
                    foreach ($decoded as $col) {
                        if (! is_array($col)) {
                            $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_column_invalid'));

                            continue 2;
                        }
                        $ck = isset($col['key']) ? (string) $col['key'] : '';
                        if ($ck === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $ck)) {
                            $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_column_key_invalid'));

                            continue 2;
                        }
                        $colKeys[] = $ck;
                        $ct = isset($col['type']) ? (string) $col['type'] : 'text';
                        if (! in_array($ct, self::TABLE_COLUMN_TYPES, true)) {
                            $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_column_type_invalid'));

                            continue 2;
                        }
                    }
                    if (count($colKeys) !== count(array_unique($colKeys))) {
                        $v->errors()->add("fields.{$i}.table_columns", $this->formatFieldError($i, $field, 'validation.document_form.table_column_keys_duplicate'));
                    }
                }
            }
        });

        return $validator->validate();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedDocumentFormPayload($request);

        $form = null;
        DB::transaction(function () use ($validated, &$form) {
            $form = DocumentForm::create([
                'form_key' => $validated['form_key'],
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'evaluation_enabled' => (bool) ($validated['evaluation_enabled'] ?? false),
                'target_document_types' => $validated['document_type'] === 'evaluation'
                    ? array_values(array_filter($validated['target_document_types'] ?? []))
                    : null,
                'layout_columns' => (int) ($validated['layout_columns'] ?? 1),
                'submission_table' => $validated['table_name'],
            ]);

            foreach ($validated['fields'] as $index => $field) {
                $form->fields()->create([
                    'field_key' => $field['field_key'],
                    'label' => $field['label_th'] ?? $field['label'] ?? $field['label_en'] ?? '',
                    'label_en' => $field['label_en'] ?? null,
                    'label_th' => $field['label_th'] ?? null,
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'is_searchable' => $this->resolveIsSearchable($field),
                    'sort_order' => $index + 1,
                    'col_span' => (int) ($field['col_span'] ?? 0),
                    'placeholder' => $field['placeholder'] ?? null,
                    'default_value' => $this->normalizeDefaultValue($field),
                    'is_readonly' => (bool) ($field['is_readonly'] ?? false),
                    'options' => $this->parseOptions($field),
                    'visibility_rules' => $this->parseJsonField($field['visibility_rules'] ?? null),
                    'required_rules' => $this->parseJsonField($field['required_rules'] ?? null),
                    'required_at_step' => $this->parseRequiredAtStep($field),
                    'validation_rules' => $this->parseJsonField($field['validation_rules'] ?? null),
                    'editable_by' => $this->parseEditableBy($field, $validated['document_type']),
                    'visible_to_org_units' => $this->parseOrgUnitIds($field),
                ]);
            }

            $form->orgUnits()->sync($validated['allowed_org_units'] ?? []);
        });

        // DDL outside DB::transaction — MySQL implicit-commits on CREATE TABLE,
        // which would orphan the outer commit (PDO "no active transaction").
        try {
            $this->schemaService->createTable($form->load('fields'));
        } catch (\Throwable $e) {
            Schema::dropIfExists($form->submission_table);
            $form->delete();
            throw $e;
        }

        $message = __('common.saved');
        if ($warning = $this->autoNumberWarning($validated['fields'], $validated['document_type'])) {
            $message .= ' — '.$warning;
        }

        return redirect()->route('settings.document-forms.index')->with('success', $message);
    }

    public function update(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $documentForm->update(['is_active' => ! $documentForm->is_active]);

            return redirect()->route('settings.document-forms.index')->with('success', __('common.saved'));
        }

        $validated = $this->validatedDocumentFormPayload($request, $documentForm);

        $needsCreateTable = false;
        DB::transaction(function () use ($validated, $documentForm, &$needsCreateTable) {
            $documentForm->update([
                'form_key' => $validated['form_key'],
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'evaluation_enabled' => (bool) ($validated['evaluation_enabled'] ?? false),
                'target_document_types' => $validated['document_type'] === 'evaluation'
                    ? array_values(array_filter($validated['target_document_types'] ?? []))
                    : null,
                'layout_columns' => (int) ($validated['layout_columns'] ?? 1),
            ]);

            $documentForm->fields()->delete();
            foreach ($validated['fields'] as $index => $field) {
                $documentForm->fields()->create([
                    'field_key' => $field['field_key'],
                    'label' => $field['label_th'] ?? $field['label'] ?? $field['label_en'] ?? '',
                    'label_en' => $field['label_en'] ?? null,
                    'label_th' => $field['label_th'] ?? null,
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'is_searchable' => $this->resolveIsSearchable($field),
                    'sort_order' => $index + 1,
                    'col_span' => (int) ($field['col_span'] ?? 0),
                    'placeholder' => $field['placeholder'] ?? null,
                    'default_value' => $this->normalizeDefaultValue($field),
                    'is_readonly' => (bool) ($field['is_readonly'] ?? false),
                    'options' => $this->parseOptions($field),
                    'visibility_rules' => $this->parseJsonField($field['visibility_rules'] ?? null),
                    'required_rules' => $this->parseJsonField($field['required_rules'] ?? null),
                    'required_at_step' => $this->parseRequiredAtStep($field),
                    'validation_rules' => $this->parseJsonField($field['validation_rules'] ?? null),
                    'editable_by' => $this->parseEditableBy($field, $validated['document_type']),
                    'visible_to_org_units' => $this->parseOrgUnitIds($field),
                ]);
            }

            $documentForm->orgUnits()->sync($validated['allowed_org_units'] ?? []);

            // First-time table creation for forms that had no dedicated table yet
            if (! $documentForm->hasDedicatedTable() && ! empty($validated['table_name'])) {
                $documentForm->update(['submission_table' => $validated['table_name']]);
                $needsCreateTable = true;
            }
        });

        // DDL outside DB::transaction — MySQL implicit-commits on CREATE/ALTER TABLE.
        // syncTable() self-heals via its own hasTable() guard if a prior DDL left partial state.
        if ($needsCreateTable) {
            $this->schemaService->createTable($documentForm->load('fields'));
        } else {
            $this->schemaService->syncTable($documentForm, $documentForm->fields()->get());
        }

        $message = __('common.updated');
        if ($warning = $this->autoNumberWarning($validated['fields'], $validated['document_type'])) {
            $message .= ' — '.$warning;
        }

        return redirect()->route('settings.document-forms.edit', $documentForm)->with('success', $message);
    }

    private function resolveIsSearchable(array $field): bool
    {
        if (! in_array($field['field_type'] ?? '', \App\Models\DocumentFormField::SEARCHABLE_TYPES, true)) {
            return false;
        }

        return (bool) ($field['is_searchable'] ?? false);
    }

    private function autoNumberWarning(array $fields, string $documentType): ?string
    {
        $hasAutoNumber = collect($fields)
            ->contains(fn ($f) => ($f['field_type'] ?? null) === 'auto_number');

        if (! $hasAutoNumber) {
            return null;
        }

        $hasConfig = RunningNumberConfig::where('document_type', $documentType)
            ->where('is_active', true)
            ->exists();

        return $hasConfig
            ? null
            : __('common.document_form_auto_number_no_config', ['type' => $documentType]);
    }

    /**
     * One-click "create report from this form" — builds a dashboard with 3 default
     * widgets (total count, breakdown by status, recent submissions table) pointing
     * at the `form:{form_key}` data source. Admin can then edit/extend in /settings/dashboards.
     */
    public function createReport(DocumentForm $documentForm): RedirectResponse
    {
        $sourceKey = 'form:'.$documentForm->form_key;
        $userId = (int) (session('user.id') ?? 1);

        $dashboard = \App\Models\ReportDashboard::create([
            'name' => __('common.form_report_dashboard_name', ['form' => $documentForm->name]),
            'description' => __('common.form_report_dashboard_desc', ['form' => $documentForm->name]),
            'layout_columns' => 2,
            'visibility' => 'all',
            'is_active' => true,
            'created_by' => $userId,
        ]);

        $widgets = [
            [
                'title' => __('common.form_report_widget_total'),
                'widget_type' => 'metric',
                'data_source' => $sourceKey,
                'config' => ['aggregation' => 'count', 'field' => 'id'],
                'col_span' => 1,
                'sort_order' => 1,
            ],
            [
                'title' => __('common.form_report_widget_by_status'),
                'widget_type' => 'chart',
                'data_source' => $sourceKey,
                'config' => ['chart_type' => 'donut', 'group_by' => 'status', 'aggregation' => 'count'],
                'col_span' => 1,
                'sort_order' => 2,
            ],
            [
                'title' => __('common.form_report_widget_recent'),
                'widget_type' => 'table',
                'data_source' => $sourceKey,
                'config' => [
                    'columns' => ['reference_no', 'status', 'org_unit_id', 'created_at'],
                    'per_page' => 10,
                ],
                'col_span' => 2,
                'sort_order' => 3,
            ],
        ];
        foreach ($widgets as $w) {
            \App\Models\ReportDashboardWidget::create(array_merge($w, ['dashboard_id' => $dashboard->id]));
        }

        return redirect()
            ->route('reports.dashboards.show', $dashboard)
            ->with('success', __('common.form_report_created'));
    }

    public function clone(DocumentForm $documentForm): RedirectResponse
    {
        $baseKey = $documentForm->form_key.'_copy';
        $newKey = $baseKey;
        $counter = 1;
        while (DocumentForm::where('form_key', $newKey)->exists()) {
            $counter++;
            $newKey = $baseKey.'_'.$counter;
        }

        $clone = DB::transaction(function () use ($documentForm, $newKey) {
            $clone = DocumentForm::create([
                'form_key' => $newKey,
                'name' => $documentForm->name.' (copy)',
                'document_type' => $documentForm->document_type,
                'description' => $documentForm->description,
                'is_active' => false,
                'layout_columns' => $documentForm->layout_columns,
                'submission_table' => null,
            ]);

            foreach ($documentForm->fields as $field) {
                $clone->fields()->create([
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'label_en' => $field->label_en,
                    'label_th' => $field->label_th,
                    'field_type' => $field->field_type,
                    'is_required' => $field->is_required,
                    'is_searchable' => $field->is_searchable,
                    'sort_order' => $field->sort_order,
                    'col_span' => $field->col_span,
                    'placeholder' => $field->placeholder,
                    'default_value' => $field->default_value,
                    'is_readonly' => $field->is_readonly,
                    'options' => $field->options,
                    'editable_by' => $field->editable_by,
                    'visibility_rules' => $field->visibility_rules,
                    'required_rules' => $field->required_rules,
                    'required_at_step' => $field->required_at_step ?? [],
                    'validation_rules' => $field->validation_rules,
                ]);
            }

            return $clone;
        });

        return redirect()->route('settings.document-forms.edit', $clone)
            ->with('success', __('common.cloned'));
    }

    public function destroy(DocumentForm $documentForm): RedirectResponse
    {
        if ($documentForm->form_key === 'evaluation_default') {
            return back()->with('error', __('common.cannot_delete_default_evaluation_form'));
        }

        if (DocumentFormSubmission::where('form_id', $documentForm->id)->exists()) {
            return redirect()->route('settings.document-forms.index')
                ->with('error', __('common.cannot_delete_document_form'));
        }

        $this->schemaService->dropTable($documentForm);
        $documentForm->fields()->delete();
        $documentForm->workflowPolicies()->delete();
        $documentForm->delete();

        return redirect()->route('settings.document-forms.index')->with('success', __('common.deleted'));
    }

    private function parseOptions(array $field): ?array
    {
        $fieldType = $field['field_type'];

        // Lookup fields store source and optional dependency config
        if ($fieldType === 'lookup') {
            $opts = ['source' => $field['lookup_source'] ?? null];
            if (! empty($field['depends_on'])) {
                $opts['depends_on'] = $field['depends_on'];
                $opts['foreign_key'] = $field['foreign_key'] ?? null;
            }

            return $opts;
        }

        // Table fields store column definitions
        if ($fieldType === 'table') {
            $raw = $field['table_columns'] ?? null;
            if ($raw) {
                $columns = json_decode($raw, true);
                if (is_array($columns) && count($columns)) {
                    return ['columns' => $columns];
                }
            }

            return null;
        }

        // Group (subform) fields store nested field definitions + repeater meta
        if ($fieldType === 'group') {
            return $this->parseGroupOptions($field);
        }

        // QR code: template + size + label position; no payload column.
        if ($fieldType === 'qr_code') {
            return $this->parseQrOptions($field);
        }

        // Formula: arithmetic expression over other field keys + optional
        // display precision. Syntax-validate at save time so admins catch
        // typos before users hit them.
        if ($fieldType === 'formula') {
            $expression = trim((string) ($field['expression'] ?? ''));
            $decimals = max(0, min(8, (int) ($field['decimals'] ?? 2)));
            if ($expression === '') {
                return null;
            }
            try {
                (new \App\Support\FormulaEvaluator)->evaluate($expression, []);
            } catch (\InvalidArgumentException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'formula' => __('common.formula_syntax_error', ['error' => $e->getMessage()]),
                ]);
            }

            return ['expression' => $expression, 'decimals' => $decimals];
        }

        // multi_select supports either a lookup source OR hardcoded options.
        // Admin picks one mode via the form-builder UI; lookup_source wins when both are present.
        if ($fieldType === 'multi_select') {
            if (! empty($field['lookup_source'])) {
                return ['source' => $field['lookup_source']];
            }
            $raw = $field['options_raw'] ?? null;
            $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));

            return count($lines) ? $lines : null;
        }

        // Select/radio/checkbox store text-based options
        if (in_array($fieldType, ['select', 'radio', 'checkbox'])) {
            $raw = $field['options_raw'] ?? null;
            $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));

            return count($lines) ? $lines : null;
        }

        return null;
    }

    /**
     * Normalise the inner-field definitions of a `group` (subform) field.
     * Drops inner fields whose type is not in GROUP_INNER_FIELD_TYPES,
     * deduplicates inner field keys, and clamps row counts.
     *
     * Stored shape (returned):
     *   [
     *     'fields' => [['key' => 'name', 'label' => '...', 'type' => 'text', 'required' => true, 'col_span' => 2], ...],
     *     'min_rows' => 0..N, 'max_rows' => 1..200,
     *     'label_singular' => string|null,
     *     'layout_columns' => 1..4,
     *   ]
     */
    private function parseGroupOptions(array $field): ?array
    {
        $raw = $field['group_options'] ?? null;
        if (! $raw) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $rawFields = is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
        $cleanFields = [];
        $seenKeys = [];
        foreach ($rawFields as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) ($row['key'] ?? ''));
            $type = (string) ($row['type'] ?? '');
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }
            if (! in_array($type, self::GROUP_INNER_FIELD_TYPES, true)) {
                continue;
            }
            $seenKeys[$key] = true;
            $cleanFields[] = [
                'key' => $key,
                'label' => (string) ($row['label_th'] ?? $row['label'] ?? $row['label_en'] ?? $key),
                'label_en' => isset($row['label_en']) ? (string) $row['label_en'] : null,
                'label_th' => isset($row['label_th']) ? (string) $row['label_th'] : null,
                'type' => $type,
                'required' => (bool) ($row['required'] ?? false),
                'col_span' => max(0, min(4, (int) ($row['col_span'] ?? 0))),
                'options' => is_array($row['options'] ?? null) ? array_values($row['options']) : null,
            ];
        }
        if (! $cleanFields) {
            return null;
        }

        return [
            'fields' => $cleanFields,
            'min_rows' => max(0, min(200, (int) ($decoded['min_rows'] ?? 0))),
            'max_rows' => max(1, min(200, (int) ($decoded['max_rows'] ?? 20))),
            'label_singular' => isset($decoded['label_singular']) ? (string) $decoded['label_singular'] : null,
            'layout_columns' => max(1, min(4, (int) ($decoded['layout_columns'] ?? 1))),
        ];
    }

    /**
     * Normalise QR-code field options: template (required, ≤1000 chars),
     * size (one of 96/128/192/256), label_position (above/below/none).
     * Returns null when the template is empty so the column stays clean.
     */
    private function parseQrOptions(array $field): ?array
    {
        $raw = $field['qr_options'] ?? null;
        if (! $raw) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
        $template = trim((string) ($decoded['template'] ?? ''));
        if ($template === '') {
            return null;
        }
        $size = (int) ($decoded['size'] ?? 128);
        if (! in_array($size, [96, 128, 192, 256], true)) {
            $size = 128;
        }
        $pos = (string) ($decoded['label_position'] ?? 'below');
        if (! in_array($pos, ['above', 'below', 'none'], true)) {
            $pos = 'below';
        }

        return [
            'template' => mb_substr($template, 0, 1000),
            'size' => $size,
            'label_position' => $pos,
        ];
    }

    /**
     * Parse a JSON string field from the form builder into an array or null.
     */
    /**
     * Normalise the field-level `default_value` string.
     *
     * Date fields accept DateExpressionResolver keywords (today/yesterday/tomorrow)
     * or a literal YYYY-MM-DD; unresolvable input is dropped.
     * Select/radio defaults must match one of the parsed `options_raw` lines —
     * otherwise the default is dropped (prevents stale defaults outliving option edits).
     * Other types keep the raw trimmed string.
     */
    private function normalizeDefaultValue(array $field): ?string
    {
        $raw = trim((string) ($field['default_value'] ?? ''));
        if ($raw === '') {
            return null;
        }

        $type = $field['field_type'] ?? '';

        if ($type === 'date') {
            $lower = strtolower($raw);
            if (in_array($lower, ['today', 'yesterday', 'tomorrow'], true)) {
                return $lower;
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null;
        }

        if (in_array($type, ['select', 'radio'], true)) {
            $options = array_values(array_filter(array_map(
                'trim',
                explode("\n", (string) ($field['options_raw'] ?? ''))
            )));

            return in_array($raw, $options, true) ? $raw : null;
        }

        return $raw;
    }

    private function parseJsonField(?string $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === '[]' || $raw === '{}') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Filter out empty visibility rules (no field selected)
        if (isset($decoded[0]['field'])) {
            $decoded = array_values(array_filter($decoded, fn ($r) => ! empty($r['field'])));
        }

        // Filter out empty validation rules (all values falsy)
        if (! isset($decoded[0])) {
            $decoded = array_filter($decoded, fn ($v) => $v !== null && $v !== '' && $v !== 0);
        }

        return count($decoded) ? $decoded : null;
    }

    /**
     * Normalise the `editable_by` field from the form builder into a clean
     * list of allowed tokens. Tokens accepted:
     *   - 'requester'           — original submitter
     *   - 'step_N'              — approver at workflow step N (must exist in
     *                             a workflow for this document_type)
     *   - 'user:{id}'           — specific active user (validated against DB)
     *
     * Stale step_N tokens (workflow was shortened) and unknown user ids are
     * dropped silently. Returns null when the submitted value equals the
     * implicit default `['requester']` so the column stays null for
     * unchanged/default fields.
     */
    private function parseEditableBy(array $field, string $documentType): ?array
    {
        $decoded = $this->decodeJsonList($field['editable_by'] ?? null);
        if ($decoded === null) {
            return null;
        }

        $allowedSteps = array_map(
            fn ($row) => 'step_'.$row['step_no'],
            $this->workflowStepsByDocType()[$documentType] ?? []
        );
        $allowedRoles = array_merge(['requester'], $allowedSteps);

        $cleanRoles = [];
        $userIds = [];
        foreach ($decoded as $token) {
            if (! is_string($token)) {
                continue;
            }
            if (in_array($token, $allowedRoles, true)) {
                $cleanRoles[] = $token;
            } elseif (preg_match('/^user:(\d+)$/', $token, $m)) {
                $userIds[] = (int) $m[1];
            }
        }
        $cleanRoles = array_values(array_unique($cleanRoles));

        $cleanUserTokens = [];
        if ($userIds) {
            $validIds = User::query()
                ->whereIn('id', array_unique($userIds))
                ->where('is_active', true)
                ->pluck('id')
                ->all();
            foreach ($validIds as $id) {
                $cleanUserTokens[] = 'user:'.(int) $id;
            }
        }

        $clean = array_values(array_merge($cleanRoles, $cleanUserTokens));

        // default `['requester']` → null so we don't bloat the column
        if ($clean === ['requester']) {
            return null;
        }

        return $clean ?: null;
    }

    private function parseRequiredAtStep(array $field): ?array
    {
        $raw = $field['required_at_step'] ?? null;
        if (! $raw) {
            return null;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded) || empty($decoded)) {
            return null;
        }
        $steps = array_values(array_filter(
            array_map(function ($s) {
                if (is_string($s) && str_starts_with($s, 'step_')) {
                    return (int) substr($s, 5);
                }
                if (is_numeric($s)) {
                    return (int) $s;
                }

                return null;
            }, $decoded),
            fn ($v) => $v !== null && $v > 0
        ));

        return $steps ?: null;
    }

    private function parseOrgUnitIds(array $field): ?array
    {
        $decoded = $this->decodeJsonList($field['visible_to_org_units'] ?? null);
        if ($decoded === null) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $decoded)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));
        if (! $ids) {
            return null;
        }

        $valid = OrgUnit::whereIn('id', $ids)->pluck('id')->all();
        $valid = array_values(array_intersect($ids, $valid));

        return $valid ?: null;
    }

    /**
     * Decode a JSON-string field into an array of scalars. Returns null for
     * empty/invalid payloads; callers can distinguish "not set" vs "empty list".
     */
    private function decodeJsonList(?string $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === '[]') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return array_values(array_filter($decoded, fn ($v) => $v !== null && $v !== ''));
    }
}
