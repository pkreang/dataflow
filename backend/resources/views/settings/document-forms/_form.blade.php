@php
    $documentForm = $documentForm ?? null;
    $isEdit = $documentForm !== null;
    $action = $isEdit ? route('settings.document-forms.update', $documentForm) : route('settings.document-forms.store');
    $cascadingRelations = \App\Support\LookupRegistry::cascadingRelations();
    $searchableTypes = \App\Models\DocumentFormField::SEARCHABLE_TYPES;
    $workflowStepsByDocType = $workflowStepsByDocType ?? [];
    $departments = $departments ?? collect();
    $companyUsers = $companyUsers ?? [];
    $initialFields = old('fields', $isEdit ? $documentForm->fields->map(function ($f) {
        $isLookup = $f->field_type === 'lookup';
        $isOldLookup = in_array($f->field_type, ['user_lookup', 'equipment_lookup']);
        $isTable = $f->field_type === 'table';
        $isGroup = $f->field_type === 'group';
        $isQrCode = $f->field_type === 'qr_code';
        $isFormula = $f->field_type === 'formula';
        $isMultiSelectLookup = $f->field_type === 'multi_select' && is_array($f->options) && isset($f->options['source']);
        return [
            'field_key' => $f->field_key,
            'label' => $f->label,
            'label_en' => $f->label_en ?? $f->label,
            'label_th' => $f->label_th ?? $f->label,
            'field_type' => $isOldLookup ? 'lookup' : $f->field_type,
            'is_required' => $f->is_required,
            'is_searchable' => $f->is_searchable,
            'is_readonly' => (bool) ($f->is_readonly ?? false),
            'placeholder' => $f->placeholder,
            'default_value' => $f->default_value ?? '',
            'options_raw' => is_array($f->options) && !isset($f->options['source']) && !isset($f->options['columns']) ? implode("\n", $f->options) : '',
            'lookup_source' => $isLookup ? ($f->options['source'] ?? '') : ($isMultiSelectLookup ? ($f->options['source'] ?? '') : ($isOldLookup ? str_replace('_lookup', '', $f->field_type) : '')),
            'depends_on' => $isLookup ? ($f->options['depends_on'] ?? '') : '',
            'foreign_key' => $isLookup ? ($f->options['foreign_key'] ?? '') : '',
            'col_span' => $f->col_span ?? 0,
            'table_columns' => $isTable ? ($f->options['columns'] ?? []) : [],
            'group_options' => $isGroup ? (is_array($f->options) ? $f->options : []) : [],
            'qr_options' => $isQrCode ? (is_array($f->options) ? $f->options : []) : [],
            'expression' => $isFormula ? (string) ($f->options['expression'] ?? '') : '',
            'decimals' => $isFormula ? (int) ($f->options['decimals'] ?? 2) : 2,
            'visibility_rules' => $f->visibility_rules ?? [],
            'required_rules' => $f->required_rules ?? [],
            'validation_rules' => (object) ($f->validation_rules ?? []),
            'editable_by' => $f->editable_by ?? ['requester'],
            'visible_to_departments' => array_map('intval', $f->visible_to_departments ?? []),
        ];
    })->values() : [
        ['field_key' => 'title', 'label' => __('common.document_form_default_title'), 'label_en' => 'Title', 'label_th' => __('common.document_form_default_title'), 'field_type' => 'text', 'is_required' => true, 'is_searchable' => true, 'is_readonly' => false, 'placeholder' => '', 'default_value' => '', 'options_raw' => '', 'lookup_source' => '', 'depends_on' => '', 'foreign_key' => '', 'col_span' => 0, 'table_columns' => [], 'visibility_rules' => [], 'validation_rules' => new \stdClass, 'editable_by' => ['requester'], 'visible_to_departments' => []],
        ['field_key' => 'amount', 'label' => __('common.document_form_default_amount'), 'label_en' => 'Amount', 'label_th' => __('common.document_form_default_amount'), 'field_type' => 'number', 'is_required' => true, 'is_searchable' => true, 'is_readonly' => false, 'placeholder' => '', 'default_value' => '', 'options_raw' => '', 'lookup_source' => '', 'depends_on' => '', 'foreign_key' => '', 'col_span' => 0, 'table_columns' => [], 'visibility_rules' => [], 'validation_rules' => new \stdClass, 'editable_by' => ['requester'], 'visible_to_departments' => []],
    ]);
@endphp

@php
    $initialDocumentType = old('document_type', $documentForm?->document_type ?? '');
    $roleLabels = [
        'requester' => __('common.role_requester'),
        'step_prefix' => __('common.role_step_prefix'),
    ];
    $departmentsJs = $departments->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])->values()->all();
    $companyUsersJs = is_array($companyUsers) ? $companyUsers : (is_iterable($companyUsers) ? iterator_to_array($companyUsers) : []);
@endphp

<div x-data="formBuilder({{ Js::from($initialFields) }}, {{ Js::from($lookupSources) }}, {{ Js::from($cascadingRelations) }}, {{ Js::from($searchableTypes) }}, {{ Js::from($workflowStepsByDocType) }}, {{ Js::from($departmentsJs) }}, {{ Js::from($initialDocumentType) }}, {{ Js::from($roleLabels) }}, {{ Js::from($companyUsersJs) }}, {{ Js::from($runningNumberConfigs ?? []) }})" x-cloak>
    @include('settings.document-forms._form-preview-modal')

    @include('settings.document-forms._form-save-confirmation')

    @if($inlineToolbar ?? false)
        {{-- Fixed actions + flow spacer rendered OUTSIDE the outer <form> on purpose.
             _form-action-buttons.blade.php contains a nested <form> for the "Create
             Report" button (edit-mode only); placing it inside the outer form would
             close the outer form prematurely per HTML5 parsing rules, causing
             form-key/name/document-type/fields inputs to fall outside and POST empty.
             Save uses form.requestSubmit() via JS, so the button doesn't need to be
             inside the <form>. --}}
        @include('settings.document-forms._form-fixed-primary-actions')
    @endif

    <form id="document-form-builder" method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-4 lg:gap-6">
            @include('settings.document-forms._form-palette')

            <div class="min-w-0 max-w-3xl mx-auto w-full">
        @if($inlineToolbar ?? false)
            <div class="card p-6">
        @endif

        @if (session('success'))
            <div class="alert-success mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('info'))
            <div class="alert-info mb-4">
                {{ session('info') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert-error mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error mb-4">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('common.document_form_key') }}</label>
                <input name="form_key" value="{{ old('form_key', $documentForm?->form_key ?? '') }}" required class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">{{ __('common.name') }}</label>
                <input name="name" value="{{ old('name', $documentForm?->name ?? '') }}" required class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">{{ __('common.document_type') }}</label>
                <select name="document_type" x-model="currentDocumentType" class="form-input mt-1">
                    @foreach(\App\Models\DocumentType::allActive() as $dt)
                        <option value="{{ $dt->code }}">{{ $dt->label() }}</option>
                    @endforeach
                </select>
                {{-- Running-number link: surfaces the prefix/format/next-value the
                     submission will receive at submit time, scoped per document_type.
                     Three branches: active config / inactive config / no config row. --}}
                <div class="mt-1.5 text-xs">
                    <template x-if="runningNumberInfo && runningNumberInfo.is_active">
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ __('common.running_number_next') }}:
                            <span class="font-mono text-blue-600 dark:text-blue-400" x-text="runningNumberInfo.preview"></span>
                            ·
                            <a :href="`/settings/running-numbers`" class="text-blue-600 dark:text-blue-400 hover:underline">{{ __('common.edit') }}</a>
                        </span>
                    </template>
                    <template x-if="runningNumberInfo && !runningNumberInfo.is_active">
                        <span class="text-orange-600 dark:text-orange-400">
                            {{ __('common.running_number_inactive_warning') }}
                            ·
                            <a href="{{ route('settings.running-numbers.index') }}" class="hover:underline">{{ __('common.running_number_setup_link') }}</a>
                        </span>
                    </template>
                    <template x-if="!runningNumberInfo">
                        <span class="text-orange-600 dark:text-orange-400" x-show="hasAutoNumberField">
                            {{ __('common.running_number_missing_warning') }}
                            ·
                            <a href="{{ route('settings.running-numbers.create') }}" class="hover:underline">{{ __('common.running_number_setup_link') }}</a>
                        </span>
                    </template>
                </div>

                <div x-show="currentDocumentType === 'evaluation'" x-cloak class="mt-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 p-3">
                    <label class="form-label text-xs font-semibold">
                        {{ __('common.target_document_types_label') }}
                    </label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('common.target_document_types_hint') }}</p>
                    @php
                        $selectedTargets = (array) old('target_document_types', $documentForm?->target_document_types ?? []);
                    @endphp
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Models\DocumentType::allActive() as $dt)
                            @if($dt->code === 'evaluation')
                                @continue
                            @endif
                            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer">
                                <input type="checkbox" name="target_document_types[]" value="{{ $dt->code }}" @checked(in_array($dt->code, $selectedTargets, true))>
                                <span class="text-xs text-slate-700 dark:text-slate-300">{{ $dt->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div>
                <label class="form-label">{{ __('common.form_layout') }}</label>
                <select name="layout_columns" x-model.number="layoutColumns" class="form-input mt-1">
                    @php $layoutCols = (int) old('layout_columns', $documentForm?->layout_columns ?? 1); @endphp
                    <option value="1" @selected($layoutCols === 1)>▌ &nbsp; {{ __('common.form_layout_1col') }}</option>
                    <option value="2" @selected($layoutCols === 2)>▌▌ &nbsp; {{ __('common.form_layout_2col') }}</option>
                    <option value="3" @selected($layoutCols === 3)>▌▌▌ &nbsp; {{ __('common.form_layout_3col') }}</option>
                    <option value="4" @selected($layoutCols === 4)>▌▌▌▌ &nbsp; {{ __('common.form_layout_4col') }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('common.form_layout_hint') }}</p>
            </div>
            <div>
                <label class="form-label">{{ __('common.table_name') }}</label>
                @if($isEdit && $documentForm?->submission_table)
                    <input name="table_name" value="{{ $documentForm->submission_table }}" readonly
                           class="form-input mt-1 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 cursor-not-allowed" />
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('common.table_name_locked') }}</p>
                @else
                    <input name="table_name" value="{{ old('table_name', $documentForm?->form_key ?? '') }}" required
                           placeholder="เช่น maintenance_requests"
                           pattern="[a-z][a-z0-9_]*" maxlength="64"
                           class="form-input mt-1" />
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('common.table_name_hint') }}</p>
                @endif
            </div>
            <div class="flex items-end gap-4 flex-wrap">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $documentForm?->is_active ?? true))>
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('common.active') }}</span>
                </label>
                <label class="inline-flex items-center gap-2" title="{{ __('common.evaluation_enabled_hint') }}">
                    <input type="checkbox" name="evaluation_enabled" value="1" @checked(old('evaluation_enabled', $documentForm?->evaluation_enabled ?? false))>
                    <span class="text-sm text-slate-600 dark:text-slate-300">{{ __('common.evaluation_enabled') }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="form-label">{{ __('common.remark') }}</label>
            <textarea name="description" rows="2" class="form-input mt-1 resize-y">{{ old('description', $documentForm?->description ?? '') }}</textarea>
        </div>

        @if($inlineToolbar ?? false)
            @include('settings.document-forms._form-inline-field-actions')
        @endif

        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2 justify-between border-b border-slate-200/80 pb-3 dark:border-slate-600">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
<h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ __('common.document_form_fields') }}</h3>
            </div>
            @unless($inlineToolbar ?? false)
                <div class="ml-auto flex shrink-0 flex-wrap justify-end gap-2">
                    <button type="button" @click="addField()" class="px-3 py-2 rounded bg-blue-600 text-white text-sm">+ {{ __('common.document_form_add_field') }}</button>
                    <button type="button" @click="addSection()" class="px-3 py-2 rounded bg-slate-500 text-white text-sm">+ {{ __('common.document_form_add_section') }}</button>
                </div>
            @endunless
        </div>

        <div data-form-canvas class="space-y-3">
        <template x-for="(field, idx) in fields" :key="field._rowId">
            <div class="form-field-card rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 p-4 space-y-3"
                 :data-field-index="idx">
                <div class="flex justify-between items-center">
                    <p class="font-medium">{{ __('common.document_form_field_short') }} <span x-text="idx + 1"></span></p>
                    <div class="space-x-2">
                        <button type="button" @click="moveUp(idx)" class="px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 text-xs">{{ __('common.move_up') }}</button>
                        <button type="button" @click="moveDown(idx)" class="px-2 py-1 rounded bg-slate-200 dark:bg-slate-700 text-xs">{{ __('common.move_down') }}</button>
                        <button type="button" @click="removeField(idx)" class="px-2 py-1 rounded bg-red-600 text-white text-xs">{{ __('common.delete') }}</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div x-show="field.field_type !== 'section'">
                        <label class="text-xs text-slate-500">{{ __('common.document_form_field_key') }}</label>
                        <input :name="`fields[${idx}][field_key]`" x-model="field.field_key" :required="field.field_type !== 'section'" class="form-input mt-1" />
                    </div>
                    <template x-if="field.field_type === 'section'">
                        <input type="hidden" :name="`fields[${idx}][field_key]`" :value="field.field_key">
                    </template>
                    <div :class="field.field_type === 'section' ? 'md:col-span-2' : ''">
                        <label class="text-xs text-slate-500" x-text="field.field_type === 'section' ? '{{ __('common.document_form_section_title') . ' (TH)' }}' : '{{ __('common.document_form_field_label') . ' (TH)' }}'"></label>
                        <input :name="`fields[${idx}][label_th]`" x-model="field.label_th" required class="form-input mt-1" />
                    </div>
                    <div :class="field.field_type === 'section' ? 'md:col-span-2' : ''">
                        <label class="text-xs text-slate-500" x-text="field.field_type === 'section' ? '{{ __('common.document_form_section_title') . ' (EN)' }}' : '{{ __('common.document_form_field_label') . ' (EN)' }}'"></label>
                        <input :name="`fields[${idx}][label_en]`" x-model="field.label_en" required class="form-input mt-1" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">{{ __('common.document_form_field_type') }}</label>
                        <select :name="`fields[${idx}][field_type]`" x-model="field.field_type" @change="if(field.field_type !== 'lookup') { field.lookup_source=''; field.depends_on=''; field.foreign_key=''; }" class="form-input mt-1">
                            <option value="text">{{ __('common.document_form_type_text') }}</option>
                            <option value="textarea">{{ __('common.document_form_type_textarea') }}</option>
                            <option value="number">{{ __('common.document_form_type_number') }}</option>
                            <option value="currency">{{ __('common.document_form_type_currency') }}</option>
                            <option value="date">{{ __('common.document_form_type_date') }}</option>
                            <option value="time">{{ __('common.document_form_type_time') }}</option>
                            <option value="datetime">{{ __('common.document_form_type_datetime') }}</option>
                            <option value="select">{{ __('common.document_form_type_select') }}</option>
                            <option value="radio">{{ __('common.document_form_type_radio') }}</option>
                            <option value="checkbox">{{ __('common.document_form_type_checkbox') }}</option>
                            <option value="email">{{ __('common.document_form_type_email') }}</option>
                            <option value="phone">{{ __('common.document_form_type_phone') }}</option>
                            <option value="file">{{ __('common.document_form_type_file') }}</option>
                            <option value="multi_file">{{ __('common.document_form_type_multi_file') }}</option>
                            <option value="image">{{ __('common.document_form_type_image') }}</option>
                            <option value="signature">{{ __('common.document_form_type_signature') }}</option>
                            <option value="multi_select">{{ __('common.document_form_type_multi_select') }}</option>
                            <option value="lookup">{{ __('common.document_form_type_lookup') }}</option>
                            <option value="table">{{ __('common.document_form_type_table') }}</option>
                            <option value="group">{{ __('common.document_form_type_group') }}</option>
                            <option value="section">{{ __('common.document_form_type_section') }}</option>
                            <option value="page_break">{{ __('common.document_form_type_page_break') }}</option>
                            <option value="qr_code">{{ __('common.document_form_type_qr_code') }}</option>
                            <option value="formula">{{ __('common.document_form_type_formula') }}</option>
                            <option value="auto_number">{{ __('common.document_form_type_auto_number') }}</option>
                        </select>
                    </div>
                    <div x-show="!['lookup','table','section','image','group','page_break','qr_code','formula'].includes(field.field_type)">
                        <label class="text-xs text-slate-500">{{ __('common.document_form_placeholder') }}</label>
                        <input :name="`fields[${idx}][placeholder]`" x-model="field.placeholder" class="form-input mt-1" />
                    </div>
                    {{-- multi_select data source: lookup list OR custom options --}}
                    <template x-if="field.field_type === 'multi_select'">
                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-3 border-t border-slate-100 dark:border-slate-700 pt-3">
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.document_form_multi_select_source') }}</label>
                                <select :name="`fields[${idx}][lookup_source]`" x-model="field.lookup_source"
                                        x-init="$nextTick(() => { if (field.lookup_source) $el.value = field.lookup_source })"
                                        class="form-input mt-1">
                                    <option value="">{{ __('common.document_form_multi_select_custom') }}</option>
                                    <template x-for="[key, src] in Object.entries(lookupSources)" :key="'ms-' + key">
                                        <template x-if="src.source_type === 'db' || (src.source_type || '') === ''">
                                            <option :value="key" x-text="(src.label_{{ app()->getLocale() }} || src.label_en || key)"></option>
                                        </template>
                                    </template>
                                </select>
                                <p class="text-xs text-slate-400 mt-1">{{ __('common.document_form_multi_select_source_help') }}</p>
                            </div>
                            <div class="md:col-span-2" x-show="!field.lookup_source">
                                <label class="text-xs text-slate-500">{{ __('common.document_form_options_hint') }}</label>
                                <textarea :name="`fields[${idx}][options_raw]`" x-model="field.options_raw" rows="2" class="form-input resize-y"></textarea>
                            </div>
                        </div>
                    </template>
                    <div class="md:col-span-2" x-show="['select','radio','checkbox'].includes(field.field_type)">
                        <label class="text-xs text-slate-500">{{ __('common.document_form_options_hint') }}</label>
                        <textarea :name="`fields[${idx}][options_raw]`" x-model="field.options_raw" rows="2" class="form-input mt-1 resize-y"></textarea>
                    </div>

                    {{-- Lookup config --}}
                    <template x-if="field.field_type === 'lookup'">
                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-3 border-t border-slate-100 dark:border-slate-700 pt-3">
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.document_form_lookup_source') }}</label>
                                <select :name="`fields[${idx}][lookup_source]`" x-model="field.lookup_source" @change="autoSuggestForeignKey(field)"
                                        x-init="$nextTick(() => { if (field.lookup_source) $el.value = field.lookup_source })"
                                        class="form-input mt-1">
                                    <option value="">{{ __('common.please_select') }}</option>
                                    <template x-for="[key, src] in Object.entries(lookupSources)" :key="key">
                                        <option :value="key" x-text="src.label_{{ app()->getLocale() }} || src.label_en"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.document_form_depends_on') }}</label>
                                <select :name="`fields[${idx}][depends_on]`" x-model="field.depends_on"
                                        x-init="$nextTick(() => { if (field.depends_on) { $el.value = field.depends_on; } })"
                                        @change="autoSuggestForeignKey(field)" class="form-input mt-1">
                                    <option value="">{{ __('common.none') }}</option>
                                    <template x-for="(other, oi) in fields" :key="'dep-'+oi">
                                        <template x-if="oi !== idx && other.field_type === 'lookup' && other.field_key">
                                            <option :value="other.field_key" x-text="other.label || other.field_key"></option>
                                        </template>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.document_form_foreign_key') }}</label>
                                <input :name="`fields[${idx}][foreign_key]`" x-model="field.foreign_key" placeholder="e.g. company_id" class="form-input mt-1" />
                            </div>
                        </div>
                    </template>

                    {{-- Table columns config --}}
                    <template x-if="field.field_type === 'table'">
                        <div class="md:col-span-3 border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_table_columns') }}</p>
                                <button type="button" @click="addTableColumn(field)" class="px-2 py-1 rounded bg-blue-600 text-white text-xs">+ {{ __('common.document_form_table_add_column') }}</button>
                            </div>
                            <template x-for="(col, ci) in field.table_columns" :key="ci">
                                <div class="space-y-1">
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <label class="text-xs text-slate-400" x-show="ci === 0">{{ __('common.document_form_field_key') }}</label>
                                            <input x-model="col.key" placeholder="key" class="form-input" />
                                        </div>
                                        <div class="flex-1">
                                            <label class="text-xs text-slate-400" x-show="ci === 0">{{ __('common.document_form_field_label') }}</label>
                                            <input x-model="col.label" placeholder="{{ __('common.document_form_field_label') }}" class="form-input" />
                                        </div>
                                        <div class="w-32">
                                            <label class="text-xs text-slate-400" x-show="ci === 0">{{ __('common.document_form_field_type') }}</label>
                                            <select x-model="col.type" class="form-input">
                                                <option value="text">{{ __('common.document_form_type_text') }}</option>
                                                <option value="number">{{ __('common.document_form_type_number') }}</option>
                                                <option value="select">{{ __('common.document_form_type_select') }}</option>
                                                <option value="checkbox">{{ __('common.document_form_type_checkbox') }}</option>
                                                <option value="date">{{ __('common.document_form_type_date') }}</option>
                                                <option value="lookup">{{ __('common.document_form_type_lookup') }}</option>
                                            </select>
                                        </div>
                                        <div class="w-36" x-show="col.type === 'lookup'">
                                            <label class="text-xs text-slate-400" x-show="ci === 0">{{ __('common.document_form_lookup_source') }}</label>
                                            <select x-model="col.lookup_source" @change="autoSuggestTableColumnForeignKey(field, col)" class="form-input">
                                                <option value="">{{ __('common.please_select') }}</option>
                                                <template x-for="[key, src] in Object.entries(lookupSources)" :key="key">
                                                    <option :value="key" x-text="src.label_{{ app()->getLocale() }} || src.label_en"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <button type="button" @click="field.table_columns.splice(ci, 1)" class="px-2 py-2 rounded bg-red-600 text-white text-xs shrink-0">{{ __('common.delete') }}</button>
                                    </div>
                                    <div class="flex items-end gap-2 pl-2" x-show="col.type === 'lookup'">
                                        <div class="w-48">
                                            <label class="text-xs text-slate-400">{{ __('common.depends_on') }}</label>
                                            <select x-model="col.depends_on" @change="autoSuggestTableColumnForeignKey(field, col)" class="form-input">
                                                <option value="">—</option>
                                                <template x-for="other in field.table_columns.filter(c => c !== col && c.type === 'lookup' && c.key)" :key="other.key">
                                                    <option :value="other.key" x-text="(other.label || other.key)"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="w-56" x-show="col.depends_on">
                                            <label class="text-xs text-slate-400">{{ __('common.foreign_key') }}</label>
                                            <input x-model="col.foreign_key" placeholder="equipment_category_id" class="form-input" />
                                        </div>
                                    </div>
                                    <div class="flex items-end gap-2 pl-2" x-show="col.type === 'number' || col.type === 'text'">
                                        <div class="flex-1">
                                            <label class="text-xs text-slate-400">{{ __('common.document_form_column_formula') }}</label>
                                            <input x-model="col.formula" placeholder="qty * unit_price" class="form-input font-mono text-sm" />
                                            <p class="text-xs text-slate-400 mt-1">{{ __('common.document_form_column_formula_hint') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <input type="hidden" :name="`fields[${idx}][table_columns]`" :value="JSON.stringify(field.table_columns)">
                        </div>
                    </template>

                    {{-- Group (subform) config — repeating block of named fields --}}
                    <template x-if="field.field_type === 'group'">
                        <div class="md:col-span-3 border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                <div>
                                    <label class="text-xs text-slate-400">{{ __('common.group_min_rows') }}</label>
                                    <input type="number" min="0" x-model.number="field.group_options.min_rows" class="form-input py-1 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-400">{{ __('common.group_max_rows') }}</label>
                                    <input type="number" min="1" max="200" x-model.number="field.group_options.max_rows" class="form-input py-1 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-400">{{ __('common.group_layout_columns') }}</label>
                                    <select x-model.number="field.group_options.layout_columns" class="form-input py-1 text-sm">
                                        <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-400">{{ __('common.group_label_singular') }}</label>
                                    <input type="text" x-model="field.group_options.label_singular" placeholder="{{ __('common.group_row_default') }}" class="form-input py-1 text-sm">
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-slate-500">{{ __('common.group_inner_fields') }}</p>
                                <button type="button" @click="addGroupInnerField(field)" class="px-2 py-1 rounded bg-blue-600 text-white text-xs">+ {{ __('common.group_add_inner_field') }}</button>
                            </div>
                            <template x-for="(inner, ii) in (field.group_options.fields || [])" :key="ii">
                                <div class="flex items-end gap-2 border-l-2 border-slate-200 dark:border-slate-700 pl-2">
                                    <div class="flex-1">
                                        <label class="text-xs text-slate-400" x-show="ii === 0">{{ __('common.document_form_field_key') }}</label>
                                        <input x-model="inner.key" placeholder="key" class="form-input py-1 text-sm" />
                                    </div>
                                    <div class="flex-1">
                                        <label class="text-xs text-slate-400" x-show="ii === 0">{{ __('common.document_form_field_label') }} TH</label>
                                        <input x-model="inner.label_th" class="form-input py-1 text-sm" />
                                    </div>
                                    <div class="w-32">
                                        <label class="text-xs text-slate-400" x-show="ii === 0">{{ __('common.document_form_field_type') }}</label>
                                        <select x-model="inner.type" class="form-input py-1 text-sm">
                                            <option value="text">text</option>
                                            <option value="textarea">textarea</option>
                                            <option value="number">number</option>
                                            <option value="currency">currency</option>
                                            <option value="date">date</option>
                                            <option value="time">time</option>
                                            <option value="datetime">datetime</option>
                                            <option value="email">email</option>
                                            <option value="phone">phone</option>
                                            <option value="select">select</option>
                                            <option value="radio">radio</option>
                                            <option value="checkbox">checkbox</option>
                                            <option value="multi_select">multi_select</option>
                                            <option value="lookup">lookup</option>
                                        </select>
                                    </div>
                                    <div class="w-20">
                                        <label class="text-xs text-slate-400" x-show="ii === 0">{{ __('common.document_form_col_span') }}</label>
                                        <select x-model.number="inner.col_span" class="form-input py-1 text-sm">
                                            <option value="0">auto</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-1 text-xs">
                                        <input type="checkbox" x-model="inner.required">
                                        <span>{{ __('common.document_form_required') }}</span>
                                    </label>
                                    <button type="button" @click="field.group_options.fields.splice(ii, 1)" class="px-2 py-1 rounded bg-red-600 text-white text-xs shrink-0">×</button>
                                </div>
                            </template>
                            <input type="hidden" :name="`fields[${idx}][group_options]`" :value="JSON.stringify(field.group_options || {})">
                        </div>
                    </template>

                    {{-- QR-code config — template + size + label position --}}
                    <template x-if="field.field_type === 'qr_code'">
                        <div class="md:col-span-3 border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.qr_template') }}</label>
                                <input type="text" x-model="field.qr_options.template"
                                       placeholder="https://example.com/verify/{ref_no}"
                                       maxlength="1000"
                                       class="form-input mt-1 text-sm font-mono">
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    {{ __('common.qr_token_help') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-slate-500">{{ __('common.qr_size') }}</label>
                                    <select x-model.number="field.qr_options.size" class="form-input mt-1 text-sm">
                                        <option value="96">96 px</option>
                                        <option value="128">128 px</option>
                                        <option value="192">192 px</option>
                                        <option value="256">256 px</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">{{ __('common.qr_label_position') }}</label>
                                    <select x-model="field.qr_options.label_position" class="form-input mt-1 text-sm">
                                        <option value="above">{{ __('common.qr_label_above') }}</option>
                                        <option value="below">{{ __('common.qr_label_below') }}</option>
                                        <option value="none">{{ __('common.qr_label_none') }}</option>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" :name="`fields[${idx}][qr_options]`" :value="JSON.stringify(field.qr_options || {})">
                        </div>
                    </template>

                    {{-- Formula config — expression + display decimals --}}
                    <template x-if="field.field_type === 'formula'">
                        <div class="md:col-span-3 border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                            <div>
                                <label class="text-xs text-slate-500">{{ __('common.formula_expression_label') }}</label>
                                <input type="text" :name="`fields[${idx}][expression]`" x-model="field.expression"
                                       placeholder="score_a + score_b + score_c"
                                       maxlength="500"
                                       class="form-input mt-1 text-sm font-mono">
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    {{ __('common.formula_help') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-slate-500">{{ __('common.formula_decimals') }}</label>
                                    <input type="number" min="0" max="8" :name="`fields[${idx}][decimals]`"
                                           x-model.number="field.decimals" class="form-input mt-1 text-sm">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="flex items-center gap-4" x-show="field.field_type !== 'section'">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" :name="`fields[${idx}][is_required]`" value="1" x-model="field.is_required">
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.document_form_required') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2" x-show="isSearchableType(field.field_type)">
                        <input type="checkbox" :name="`fields[${idx}][is_searchable]`" value="1" x-model="field.is_searchable">
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.document_form_searchable') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" :name="`fields[${idx}][is_readonly]`" value="1" x-model="field.is_readonly">
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.field_readonly') }}</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">{{ __('common.document_form_col_span') }}</span>
                        <select :name="`fields[${idx}][col_span]`" x-model.number="field.col_span" class="form-input py-1 px-2 text-xs">
                            <option value="0">{{ __('common.document_form_col_span_auto') }}</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                </div>

                {{-- Advanced: Visibility Rules + Validation Rules --}}
                <div x-data="{ showAdvanced: false }" x-show="field.field_type !== 'section'" class="border-t border-slate-100 dark:border-slate-700 pt-2 mt-2">
                    <button type="button" @click="showAdvanced = !showAdvanced" class="text-xs text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                        <svg class="w-3 h-3 transition-transform" :class="showAdvanced ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        {{ __('common.advanced_settings') ?? 'Advanced Settings' }}
                    </button>

                    <div x-show="showAdvanced" x-cloak class="mt-3 space-y-4">
                        {{-- Visibility Rules --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.visibility_rules') ?? 'Visibility Rules' }}</p>
                            <template x-for="(rule, ri) in (field.visibility_rules || [])" :key="ri">
                                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,2fr)_auto] items-center gap-2 mb-2">
                                    <select x-model="rule.field" class="form-input py-1 px-2 text-xs min-w-0">
                                        <option value="">{{ __('common.select_field') }}</option>
                                        <template x-for="(other, oi) in fields" :key="'vis-'+oi">
                                            <template x-if="oi !== idx && other.field_key && other.field_type !== 'section'">
                                                <option :value="other.field_key" x-text="other.label || other.field_key"></option>
                                            </template>
                                        </template>
                                    </select>
                                    <select x-model="rule.operator" class="form-input py-1 px-2 text-xs w-20 text-center">
                                        <option value="equals">{{ __('common.op_equals') }}</option>
                                        <option value="not_equals">{{ __('common.op_not_equals') }}</option>
                                        <option value="is_empty">{{ __('common.op_is_empty') }}</option>
                                        <option value="is_not_empty">{{ __('common.op_is_not_empty') }}</option>
                                        <option value="greater_than">{{ __('common.op_greater_than') }}</option>
                                        <option value="less_than">{{ __('common.op_less_than') }}</option>
                                    </select>
                                    <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)" x-model="rule.value" placeholder="{{ __('common.value') }}" class="form-input py-1 px-2 text-xs min-w-0" />
                                    <button type="button" @click="field.visibility_rules.splice(ri, 1)" class="text-red-500 hover:text-red-700 text-xs">&times;</button>
                                </div>
                            </template>
                            <button type="button" @click="if(!field.visibility_rules) field.visibility_rules = []; field.visibility_rules.push({field:'', operator:'equals', value:''})" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('common.add_condition') ?? 'Add condition' }}</button>
                            <input type="hidden" :name="`fields[${idx}][visibility_rules]`" :value="JSON.stringify(field.visibility_rules || [])">
                        </div>

                        {{-- Required Rules — only meaningful when is_required = false --}}
                        <div x-show="!field.is_required">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.required_rules') }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">{{ __('common.required_rules_help') }}</p>
                            <template x-for="(rule, ri) in (field.required_rules || [])" :key="'req-'+ri">
                                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,2fr)_auto] items-center gap-2 mb-2">
                                    <select x-model="rule.field" class="form-input py-1 px-2 text-xs min-w-0">
                                        <option value="">{{ __('common.select_field') }}</option>
                                        <template x-for="(other, oi) in fields" :key="'req-fld-'+oi">
                                            <template x-if="oi !== idx && other.field_key && other.field_type !== 'section'">
                                                <option :value="other.field_key" x-text="other.label || other.field_key"></option>
                                            </template>
                                        </template>
                                    </select>
                                    <select x-model="rule.operator" class="form-input py-1 px-2 text-xs w-20 text-center">
                                        <option value="equals">{{ __('common.op_equals') }}</option>
                                        <option value="not_equals">{{ __('common.op_not_equals') }}</option>
                                        <option value="is_empty">{{ __('common.op_is_empty') }}</option>
                                        <option value="is_not_empty">{{ __('common.op_is_not_empty') }}</option>
                                        <option value="greater_than">{{ __('common.op_greater_than') }}</option>
                                        <option value="less_than">{{ __('common.op_less_than') }}</option>
                                    </select>
                                    <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)" x-model="rule.value" placeholder="{{ __('common.value') }}" class="form-input py-1 px-2 text-xs min-w-0" />
                                    <button type="button" @click="field.required_rules.splice(ri, 1)" class="text-red-500 hover:text-red-700 text-xs">&times;</button>
                                </div>
                            </template>
                            <button type="button" @click="if(!field.required_rules) field.required_rules = []; field.required_rules.push({field:'', operator:'equals', value:''})" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('common.add_condition') ?? 'Add condition' }}</button>
                            <input type="hidden" :name="`fields[${idx}][required_rules]`" :value="JSON.stringify(field.required_rules || [])">
                        </div>

                        {{-- Validation Rules --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.validation_rules') ?? 'Validation Rules' }}</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                <div x-show="['text','textarea','email','phone'].includes(field.field_type)">
                                    <label class="text-xs text-slate-400">{{ __('common.min_length') ?? 'Min length' }}</label>
                                    <input type="number" min="0" x-model.number="field.validation_rules.min_length" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="['text','textarea','email','phone'].includes(field.field_type)">
                                    <label class="text-xs text-slate-400">{{ __('common.max_length') ?? 'Max length' }}</label>
                                    <input type="number" min="0" x-model.number="field.validation_rules.max_length" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="['text','email','phone'].includes(field.field_type)">
                                    <label class="text-xs text-slate-400">{{ __('common.regex_pattern') ?? 'Regex pattern' }}</label>
                                    <input type="text" x-model="field.validation_rules.regex" placeholder="^[A-Z].*" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="['number','currency'].includes(field.field_type)">
                                    <label class="text-xs text-slate-400">{{ __('common.min_value') ?? 'Min value' }}</label>
                                    <input type="number" step="0.01" x-model.number="field.validation_rules.min" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="['number','currency'].includes(field.field_type)">
                                    <label class="text-xs text-slate-400">{{ __('common.max_value') ?? 'Max value' }}</label>
                                    <input type="number" step="0.01" x-model.number="field.validation_rules.max" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="field.field_type === 'date'">
                                    <label class="text-xs text-slate-400">{{ __('common.min_date') }}</label>
                                    <input type="text" x-model="field.validation_rules.min_date" placeholder="today / 2026-01-01" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <div x-show="field.field_type === 'date'">
                                    <label class="text-xs text-slate-400">{{ __('common.max_date') }}</label>
                                    <input type="text" x-model="field.validation_rules.max_date" placeholder="today / 2026-12-31" class="form-input py-1 px-2 text-xs mt-1" />
                                </div>
                                <template x-if="field.field_type === 'date'">
                                    <div>
                                        <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                        <input type="text" :name="`fields[${idx}][default_value]`" x-model="field.default_value" placeholder="today / 2026-01-01" class="form-input py-1 px-2 text-xs mt-1" />
                                    </div>
                                </template>
                                <template x-if="['text','textarea','email','phone'].includes(field.field_type)">
                                    <div>
                                        <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                        <input type="text" :name="`fields[${idx}][default_value]`" x-model="field.default_value" class="form-input py-1 px-2 text-xs mt-1" />
                                    </div>
                                </template>
                                <template x-if="['number','currency'].includes(field.field_type)">
                                    <div>
                                        <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                        <input type="number" step="0.01" :name="`fields[${idx}][default_value]`" x-model="field.default_value" class="form-input py-1 px-2 text-xs mt-1" />
                                    </div>
                                </template>
                                <template x-if="['select','radio'].includes(field.field_type)">
                                    <div>
                                        <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                        <select :name="`fields[${idx}][default_value]`" x-model="field.default_value" class="form-input py-1 px-2 text-xs mt-1">
                                            <option value="">—</option>
                                            <template x-for="opt in (field.options_raw || '').split('\n').map(s => s.trim()).filter(Boolean)" :key="opt">
                                                <option :value="opt" x-text="opt"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                            </div>
                            <p x-show="field.field_type === 'date'" class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                {{ __('common.date_expression_help') }}
                            </p>
                            <input type="hidden" :name="`fields[${idx}][validation_rules]`" :value="JSON.stringify(field.validation_rules || {})">
                        </div>

                        {{-- Field-level permissions: who can edit this field + which departments see it --}}
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.field_editable_by') }}</p>
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                <template x-for="role in availableRoles" :key="role.value">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" :value="role.value"
                                               :checked="(field.editable_by || []).includes(role.value)"
                                               @change="toggleArrayValue(field, 'editable_by', role.value)"
                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                        <span x-text="role.label"></span>
                                    </label>
                                </template>
                            </div>

                            {{-- Per-user editable_by: chip selector + searchable suggestion list --}}
                            @if(count($companyUsersJs))
                            <div class="mt-2" x-data="{ open: false, query: '' }">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.field_editable_by_users') }}</p>
                                <div class="flex flex-wrap items-center gap-1">
                                    <template x-for="u in selectedEditableUsers(field)" :key="u.id">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs">
                                            <span x-text="u.name"></span>
                                            <button type="button" class="text-blue-500 hover:text-red-600" @click="removeEditableUser(field, u.id)">×</button>
                                        </span>
                                    </template>
                                    <button type="button" class="text-xs text-blue-600 hover:underline ml-1" @click="open = !open">
                                        + {{ __('common.field_editable_by_users_add') }}
                                    </button>
                                </div>
                                <div x-show="open" x-cloak class="mt-1 p-2 border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-800">
                                    <input type="text" x-model="query" placeholder="{{ __('common.field_editable_by_users_search') }}"
                                           class="form-input py-1 px-2 text-xs w-full mb-1" />
                                    <div class="max-h-40 overflow-y-auto">
                                        <template x-for="u in availableEditableUsers(field, query)" :key="u.id">
                                            <button type="button"
                                                    class="block w-full text-left text-xs px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300"
                                                    @click="addEditableUser(field, u.id); query = ''">
                                                <span x-text="u.name"></span>
                                            </button>
                                        </template>
                                        <p x-show="!availableEditableUsers(field, query).length" class="text-xs text-slate-400 dark:text-slate-500 px-2 py-1">
                                            {{ __('common.field_editable_by_users_empty') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <p x-show="!(field.editable_by || []).length" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                {{ __('common.field_editable_by_none_hint') }}
                            </p>
                            <input type="hidden" :name="`fields[${idx}][editable_by]`" :value="JSON.stringify(field.editable_by || [])">
                        </div>

                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.field_visible_to_departments') }}</p>
                            @if(count($departmentsJs))
                                <div class="flex flex-wrap gap-x-4 gap-y-1 max-h-40 overflow-y-auto p-1 rounded border border-slate-200 dark:border-slate-600">
                                    <template x-for="dept in departments" :key="dept.id">
                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" :value="dept.id"
                                                   :checked="(field.visible_to_departments || []).map(Number).includes(dept.id)"
                                                   @change="toggleArrayValue(field, 'visible_to_departments', dept.id, true)"
                                                   class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                            <span x-text="dept.name"></span>
                                        </label>
                                    </template>
                                </div>
                                <p x-show="!(field.visible_to_departments || []).length" class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    {{ __('common.field_visible_to_departments_all_hint') }}
                                </p>
                            @else
                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.field_visible_to_departments_empty') }}</p>
                            @endif
                            <input type="hidden" :name="`fields[${idx}][visible_to_departments]`" :value="JSON.stringify(field.visible_to_departments || [])">
                        </div>
                    </div>
                </div>
            </div>
        </template>
        </div>{{-- /data-form-canvas --}}

        @if($inlineToolbar ?? false)
            </div>
        @endif
            </div>{{-- /center column --}}
        </div>{{-- /grid --}}

    </form>

</div>

<script>
    function ensureFieldRowId(field) {
        const f = { ...field };
        if (!f._rowId) {
            f._rowId = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'row_' + Math.random().toString(36).slice(2, 11);
        }
        if (!Array.isArray(f.editable_by)) f.editable_by = ['requester'];
        if (!Array.isArray(f.visible_to_departments)) f.visible_to_departments = [];
        if (!Array.isArray(f.required_rules)) f.required_rules = [];
        // Group fields need a populated options object so the editor's x-models bind cleanly.
        if (f.field_type === 'group') {
            const g = (typeof f.group_options === 'object' && f.group_options !== null) ? f.group_options : {};
            f.group_options = {
                fields: Array.isArray(g.fields) ? g.fields : [],
                min_rows: typeof g.min_rows === 'number' ? g.min_rows : 0,
                max_rows: typeof g.max_rows === 'number' ? g.max_rows : 20,
                layout_columns: typeof g.layout_columns === 'number' ? g.layout_columns : 1,
                label_singular: typeof g.label_singular === 'string' ? g.label_singular : '',
            };
        }
        // QR-code fields: same defaulting trick so x-model bindings always have a target.
        if (f.field_type === 'qr_code') {
            const q = (typeof f.qr_options === 'object' && f.qr_options !== null) ? f.qr_options : {};
            f.qr_options = {
                template: typeof q.template === 'string' ? q.template : '',
                size: typeof q.size === 'number' ? q.size : 128,
                label_position: typeof q.label_position === 'string' ? q.label_position : 'below',
            };
        }
        // Formula fields: ensure expression + decimals defaults so x-model bindings work.
        if (f.field_type === 'formula') {
            if (typeof f.expression !== 'string') f.expression = '';
            if (typeof f.decimals !== 'number') f.decimals = 2;
        }
        return f;
    }

    function formBuilder(initialFields, lookupSources, cascadingRelations, searchableTypes, workflowStepsByDocType, departments, initialDocumentType, roleLabels, companyUsers, runningNumberConfigs) {
        const SEARCHABLE_TYPES = searchableTypes || [];
        const defaultSearchable = (type) => SEARCHABLE_TYPES.includes(type);
        return {
            fields: (initialFields || []).map((f) => ensureFieldRowId(f)),
            lookupSources: lookupSources || {},
            cascadingRelations: cascadingRelations || {},
            searchableTypes: SEARCHABLE_TYPES,
            workflowStepsByDocType: workflowStepsByDocType || {},
            departments: departments || [],
            companyUsers: companyUsers || [],
            runningNumberConfigs: runningNumberConfigs || {},
            // Live snapshot of the running-number config for the currently selected
            // document_type. `undefined` = no config row exists; `{is_active:false}`
            // = row exists but disabled. The two cases drive different UI hints.
            get runningNumberInfo() {
                return this.runningNumberConfigs[this.currentDocumentType] || null;
            },
            get hasAutoNumberField() {
                return this.fields.some(f => f.field_type === 'auto_number');
            },
            userPickerQuery: {},
            currentDocumentType: initialDocumentType || '',
            roleLabels: roleLabels || { requester: 'Requester', step_prefix: 'Step' },
            // Reactive form-wide column count — synced via x-model.number on
            // the layout_columns <select>. Replaces the old DOM-read getter,
            // which was non-reactive (preview modal showed stale value when
            // user changed the dropdown after opening the preview).
            layoutColumns: 1,
            isSearchableType(type) { return SEARCHABLE_TYPES.includes(type); },
            get availableRoles() {
                const steps = this.workflowStepsByDocType[this.currentDocumentType] || [];
                const roles = [{ value: 'requester', label: this.roleLabels.requester }];
                for (const s of steps) {
                    const suffix = s.name ? ': ' + s.name : '';
                    roles.push({ value: 'step_' + s.step_no, label: this.roleLabels.step_prefix + ' ' + s.step_no + suffix });
                }
                return roles;
            },
            // Users currently selected for a field's editable_by (decoded from
            // 'user:{id}' tokens). Lookup against companyUsers gives display name.
            selectedEditableUsers(field) {
                const userIds = (field.editable_by || [])
                    .filter((t) => typeof t === 'string' && t.startsWith('user:'))
                    .map((t) => parseInt(t.slice(5), 10))
                    .filter((n) => !isNaN(n));
                return userIds
                    .map((id) => this.companyUsers.find((u) => u.id === id))
                    .filter((u) => !!u);
            },
            availableEditableUsers(field, query) {
                const q = (query || '').toLowerCase().trim();
                const selectedIds = new Set(this.selectedEditableUsers(field).map((u) => u.id));
                let pool = this.companyUsers.filter((u) => !selectedIds.has(u.id));
                if (q) {
                    pool = pool.filter((u) => (u.name || '').toLowerCase().includes(q));
                }
                return pool.slice(0, 25);
            },
            addEditableUser(field, userId) {
                if (!Array.isArray(field.editable_by)) field.editable_by = [];
                const token = 'user:' + userId;
                if (!field.editable_by.includes(token)) {
                    field.editable_by.push(token);
                }
            },
            removeEditableUser(field, userId) {
                if (!Array.isArray(field.editable_by)) return;
                const token = 'user:' + userId;
                field.editable_by = field.editable_by.filter((t) => t !== token);
            },
            toggleArrayValue(field, prop, value, asNumber = false) {
                if (!Array.isArray(field[prop])) field[prop] = [];
                const cast = (v) => asNumber ? Number(v) : v;
                const idx = field[prop].findIndex((v) => cast(v) === cast(value));
                if (idx >= 0) {
                    field[prop].splice(idx, 1);
                } else {
                    field[prop].push(cast(value));
                }
            },
            showPreview: false,
            showSaveConfirm: false,
            previewTitle: '',
            init() {
                this.$nextTick(() => {
                    this.mountDragDrop();
                    const sel = document.querySelector('select[name="layout_columns"]');
                    if (sel) this.layoutColumns = parseInt(sel.value || '1', 10) || 1;
                });
            },
            mountDragDrop() {
                const Sortable = window.Sortable;
                if (!Sortable) return;
                const root = this.$el;

                // Palette groups: clone-mode drag source (no internal sort).
                root.querySelectorAll('[data-palette-group]').forEach(group => {
                    if (group.dataset.sortableInit) return;
                    group.dataset.sortableInit = '1';
                    Sortable.create(group, {
                        group: { name: 'form-fields', pull: 'clone', put: false },
                        sort: false,
                        animation: 150,
                    });
                });

                // Canvas: accept drops + reorder within.
                const canvas = root.querySelector('[data-form-canvas]');
                if (canvas && !canvas.dataset.sortableInit) {
                    canvas.dataset.sortableInit = '1';
                    Sortable.create(canvas, {
                        group: { name: 'form-fields', pull: true, put: true },
                        animation: 150,
                        handle: '.form-field-card',
                        ghostClass: 'opacity-30',
                        onAdd: (evt) => {
                            // Sortable inserted a cloned palette button into the DOM —
                            // remove it (Alpine should be the only source of truth)
                            // then call addField with the dropped type + position.
                            const node = evt.item;
                            const type = node.getAttribute('data-field-type');
                            const insertAt = evt.newIndex;
                            node.remove();
                            if (type) this.addField(type, insertAt);
                        },
                        onUpdate: (evt) => {
                            // Reorder within canvas: sync Alpine fields array
                            // with the DOM's new order. Use oldIndex/newIndex,
                            // not children, to avoid template nodes in count.
                            const { oldIndex, newIndex } = evt;
                            if (oldIndex === newIndex || oldIndex == null || newIndex == null) return;
                            const arr = this.fields;
                            const [moved] = arr.splice(oldIndex, 1);
                            arr.splice(newIndex, 0, moved);
                        },
                    });
                }
            },
            openSaveConfirm() {
                this.showSaveConfirm = true;
            },
            confirmSave() {
                this.showSaveConfirm = false;
                const form = document.getElementById('document-form-builder');
                if (!form) {
                    return;
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            },
            openPreview() {
                const form = this.$el.querySelector('form');
                const name = form?.querySelector('input[name="name"]')?.value?.trim() || '';
                const key = form?.querySelector('input[name="form_key"]')?.value?.trim() || '';
                this.previewTitle = name || key || '';
                this.showPreview = true;
            },
            previewGridStyle(field) {
                const cols = this.layoutColumns;
                const span = (field.col_span && cols > 1) ? Math.min(field.col_span, cols) : 1;
                return span > 1 ? `grid-column: span ${span}` : '';
            },
            addField(type = 'text', insertAt = null) {
                const base = {
                    field_key: '', label: '', label_en: '', label_th: '',
                    field_type: type,
                    is_required: false,
                    is_searchable: defaultSearchable(type),
                    is_readonly: false,
                    placeholder: '', default_value: '',
                    options_raw: '', lookup_source: '', depends_on: '', foreign_key: '',
                    col_span: 0, table_columns: [],
                    visibility_rules: [], validation_rules: {},
                };
                // Type-specific seeding so the row renders with sensible defaults
                if (type === 'section') {
                    let max = 0;
                    for (const f of this.fields) {
                        const m = /^section_(\d+)$/.exec(f.field_key || '');
                        if (m) max = Math.max(max, parseInt(m[1], 10));
                    }
                    base.field_key = 'section_' + (max + 1);
                }
                if (type === 'page_break') {
                    let max = 0;
                    for (const f of this.fields) {
                        const m = /^page_break_(\d+)$/.exec(f.field_key || '');
                        if (m) max = Math.max(max, parseInt(m[1], 10));
                    }
                    base.field_key = 'page_break_' + (max + 1);
                }
                const row = ensureFieldRowId(base);
                if (insertAt === null || insertAt < 0 || insertAt >= this.fields.length) {
                    this.fields.push(row);
                } else {
                    this.fields.splice(insertAt, 0, row);
                }
            },
            addSection() { this.addField('section'); },
            addTableColumn(field) {
                if (!field.table_columns) field.table_columns = [];
                field.table_columns.push({key: '', label: '', type: 'text', lookup_source: '', depends_on: '', foreign_key: '', formula: ''});
            },
            addGroupInnerField(field) {
                if (!field.group_options || typeof field.group_options !== 'object') {
                    field.group_options = { fields: [], min_rows: 0, max_rows: 20, layout_columns: 1, label_singular: '' };
                }
                if (!Array.isArray(field.group_options.fields)) field.group_options.fields = [];
                field.group_options.fields.push({ key: '', label_th: '', label_en: '', type: 'text', required: false, col_span: 0, options: [] });
            },
            autoSuggestTableColumnForeignKey(field, col) {
                if (!col.lookup_source || !col.depends_on) {
                    col.foreign_key = '';
                    return;
                }
                const parentCol = (field.table_columns || []).find(c => c.key === col.depends_on);
                if (!parentCol || !parentCol.lookup_source) return;
                const relations = this.cascadingRelations[col.lookup_source];
                if (relations && relations[parentCol.lookup_source]) {
                    col.foreign_key = relations[parentCol.lookup_source];
                }
            },
            removeField(idx) {
                this.fields.splice(idx, 1);
            },
            moveUp(idx) {
                if (idx <= 0) return;
                [this.fields[idx - 1], this.fields[idx]] = [this.fields[idx], this.fields[idx - 1]];
            },
            moveDown(idx) {
                if (idx >= this.fields.length - 1) return;
                [this.fields[idx + 1], this.fields[idx]] = [this.fields[idx], this.fields[idx + 1]];
            },
            autoSuggestForeignKey(field) {
                if (!field.lookup_source || !field.depends_on) {
                    field.foreign_key = '';
                    return;
                }
                // Find the parent field's lookup_source
                const parentField = this.fields.find(f => f.field_key === field.depends_on);
                if (!parentField || !parentField.lookup_source) return;
                const relations = this.cascadingRelations[field.lookup_source];
                if (relations && relations[parentField.lookup_source]) {
                    field.foreign_key = relations[parentField.lookup_source];
                }
            }
        }
    }
</script>
