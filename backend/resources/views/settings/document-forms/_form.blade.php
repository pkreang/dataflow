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
    $initialDocumentType = old('document_type', $documentForm?->document_type ?? (($preset ?? [])['document_type'] ?? ''));
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
        @include('settings.document-forms._form-fixed-primary-actions')
    @endif

    <form id="document-form-builder" method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- Page-level tabs --}}
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="flex gap-0 -mb-px">
                <button type="button" @click="pageTab = 'settings'"
                        class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors"
                        :class="pageTab === 'settings'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                    {{ __('common.form_tab_settings') }}
                </button>
                <button type="button" @click="pageTab = 'fields'"
                        class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-2"
                        :class="pageTab === 'fields'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                    {{ __('common.form_tab_fields') }}
                    <span x-show="fields.length > 0"
                          class="inline-flex items-center justify-center min-w-[18px] px-1 rounded-full text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"
                          x-text="fields.length"></span>
                </button>
            </nav>
        </div>

        {{-- Settings tab --}}
        <div x-show="pageTab === 'settings'" class="space-y-5">

        @if (session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="alert-info">{{ session('info') }}</div>
        @endif
        @if (session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert-error">
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
                    <option value="">{{ __('common.please_select') }}</option>
                    @foreach(\App\Models\DocumentType::allActive() as $dt)
                        <option value="{{ $dt->code }}" @selected($initialDocumentType === $dt->code)>{{ $dt->label() }}</option>
                    @endforeach
                </select>
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
                    <option value="1" @selected($layoutCols === 1)>&#9612; &nbsp; {{ __('common.form_layout_1col') }}</option>
                    <option value="2" @selected($layoutCols === 2)>&#9612;&#9612; &nbsp; {{ __('common.form_layout_2col') }}</option>
                    <option value="3" @selected($layoutCols === 3)>&#9612;&#9612;&#9612; &nbsp; {{ __('common.form_layout_3col') }}</option>
                    <option value="4" @selected($layoutCols === 4)>&#9612;&#9612;&#9612;&#9612; &nbsp; {{ __('common.form_layout_4col') }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('common.form_layout_hint') }}</p>
                <button type="button"
                        @click="pageTab = 'fields'; rightPanelMode = 'preview'; deselectField()"
                        class="mt-2 hidden lg:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                        :class="rightPanelMode === 'preview'
                            ? 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300'
                            : 'bg-white border-slate-200 text-slate-600 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span>{{ __('common.form_builder_inline_preview') }}</span>
                </button>
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

        @if($departments->count())
        <div>
            <label class="form-label">{{ __('common.document_form_allowed_departments') }}</label>
            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ __('common.document_form_allowed_departments_hint') }}</p>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                @foreach($departments as $dept)
                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                        <input type="checkbox"
                            name="allowed_departments[]"
                            value="{{ $dept->id }}"
                            @checked(in_array($dept->id, old('allowed_departments', $allowedDepartmentIds ?? [])))
                            class="rounded border-slate-300 text-blue-600 dark:border-slate-600">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $dept->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        </div>{{-- /settings tab --}}

        {{-- Fields tab: 3-col DnD builder --}}
        <div x-show="pageTab === 'fields'">

        @unless($inlineToolbar ?? false)
        <div class="flex items-center justify-end gap-2 mb-3">
            <button type="button" @click="addField()" class="px-3 py-2 rounded bg-blue-600 text-white text-sm">+ {{ __('common.document_form_add_field') }}</button>
            <button type="button" @click="addSection()" class="px-3 py-2 rounded bg-slate-500 text-white text-sm">+ {{ __('common.document_form_add_section') }}</button>
        </div>
        @endunless

        <div class="grid grid-cols-1 gap-4 lg:gap-6 lg:grid-cols-[230px_1fr_380px]">
            @include('settings.document-forms._form-palette')

            <div class="min-w-0 w-full">
        @if($inlineToolbar ?? false)
            <div class="card p-6">
        @endif

        @if($inlineToolbar ?? false)
            @include('settings.document-forms._form-inline-field-actions')
        @endif

        {{-- Compact field canvas --}}
        <div data-form-canvas class="space-y-1.5">
        <template x-for="(field, idx) in fields" :key="field._rowId">
            <div class="form-field-card group flex flex-col rounded-lg border bg-white dark:bg-slate-900/20 transition-all cursor-pointer select-none"
                 :class="selectedFieldIdx === idx
                     ? 'border-blue-400 dark:border-blue-500 ring-2 ring-blue-200 dark:ring-blue-700/50 shadow-sm'
                     : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'"
                 @click="selectField(idx)"
                 :data-field-index="idx">
                {{-- Compact card row --}}
                <div class="flex items-center gap-2 px-3 py-2.5">
                    <div class="drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-500 dark:text-slate-600 dark:hover:text-slate-400 shrink-0 touch-none"
                         @click.stop>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 6zm0 6a2 2 0 10.001 4.001A2 2 0 007 12zm6-8a2 2 0 10.001 4.001A2 2 0 0013 4zm0 6a2 2 0 10.001 4.001A2 2 0 0013 10zm0 6a2 2 0 10.001 4.001A2 2 0 0013 16z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-mono text-slate-400 dark:text-slate-500 shrink-0 w-5 text-right" x-text="idx + 1"></span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 shrink-0 max-w-[100px] truncate"
                          :class="selectedFieldIdx === idx ? 'bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : ''"
                          x-text="field.field_type"></span>
                    <span class="flex-1 text-sm font-medium truncate min-w-0"
                          :class="(field.label_th || field.label_en) ? 'text-slate-700 dark:text-slate-200' : 'italic text-slate-400 dark:text-slate-500'"
                          x-text="field.label_th || field.label_en || '(ยังไม่ตั้งชื่อ)'"></span>
                    <span class="text-xs text-slate-400 dark:text-slate-500 font-mono shrink-0 hidden sm:block"
                          x-show="field.field_key && field.field_type !== 'section' && field.field_type !== 'page_break'"
                          x-text="`[${field.field_key}]`"></span>
                    <div class="flex items-center gap-1 shrink-0 ml-1">
                        <span x-show="field.is_required" class="text-xs text-red-500 font-bold leading-none" title="{{ __('common.document_form_required') }}">*</span>
                        <button type="button" @click.stop="removeField(idx)"
                                class="p-1 rounded text-slate-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 dark:text-slate-600 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                                title="{{ __('common.delete') }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                {{-- Hidden inputs — all properties for POST --}}
                <input type="hidden" :name="`fields[${idx}][field_key]`" :value="field.field_key ?? ''">
                <input type="hidden" :name="`fields[${idx}][label_th]`" :value="field.label_th ?? ''">
                <input type="hidden" :name="`fields[${idx}][label_en]`" :value="field.label_en ?? ''">
                <input type="hidden" :name="`fields[${idx}][field_type]`" :value="field.field_type ?? 'text'">
                <input type="hidden" :name="`fields[${idx}][is_required]`" :value="field.is_required ? '1' : '0'">
                <input type="hidden" :name="`fields[${idx}][is_searchable]`" :value="field.is_searchable ? '1' : '0'">
                <input type="hidden" :name="`fields[${idx}][is_readonly]`" :value="field.is_readonly ? '1' : '0'">
                <input type="hidden" :name="`fields[${idx}][placeholder]`" :value="field.placeholder ?? ''">
                <input type="hidden" :name="`fields[${idx}][default_value]`" :value="field.default_value ?? ''">
                <input type="hidden" :name="`fields[${idx}][options_raw]`" :value="field.options_raw ?? ''">
                <input type="hidden" :name="`fields[${idx}][col_span]`" :value="field.col_span ?? 0">
                <input type="hidden" :name="`fields[${idx}][lookup_source]`" :value="field.lookup_source ?? ''">
                <input type="hidden" :name="`fields[${idx}][depends_on]`" :value="field.depends_on ?? ''">
                <input type="hidden" :name="`fields[${idx}][foreign_key]`" :value="field.foreign_key ?? ''">
                <input type="hidden" :name="`fields[${idx}][expression]`" :value="field.expression ?? ''">
                <input type="hidden" :name="`fields[${idx}][decimals]`" :value="field.decimals ?? 2">
                <input type="hidden" :name="`fields[${idx}][table_columns]`" :value="JSON.stringify(field.table_columns ?? [])">
                <input type="hidden" :name="`fields[${idx}][group_options]`" :value="JSON.stringify(field.group_options ?? {})">
                <input type="hidden" :name="`fields[${idx}][qr_options]`" :value="JSON.stringify(field.qr_options ?? {})">
                <input type="hidden" :name="`fields[${idx}][visibility_rules]`" :value="JSON.stringify(field.visibility_rules ?? [])">
                <input type="hidden" :name="`fields[${idx}][required_rules]`" :value="JSON.stringify(field.required_rules ?? [])">
                <input type="hidden" :name="`fields[${idx}][validation_rules]`" :value="JSON.stringify(field.validation_rules ?? {})">
                <input type="hidden" :name="`fields[${idx}][editable_by]`" :value="JSON.stringify(field.editable_by ?? [])">
                <input type="hidden" :name="`fields[${idx}][visible_to_departments]`" :value="JSON.stringify(field.visible_to_departments ?? [])">
                <input type="hidden" :name="`fields[${idx}][required_at_step]`" :value="JSON.stringify((field.required_at_step ?? []).map(n => 'step_' + n))">
            </div>
        </template>
        </div>{{-- /data-form-canvas --}}

        {{-- Bottom duplicate of "+ Add Field" buttons --}}
        @include('settings.document-forms._form-inline-field-actions')

        @if($inlineToolbar ?? false)
            </div>
        @endif
            </div>{{-- /center column --}}

            {{-- Right panel: Preview + Properties tabs — always visible lg+ --}}
            <div class="hidden lg:flex flex-col sticky self-start"
                 style="top: 5rem; max-height: calc(100vh - 6rem)">
                <div class="card flex flex-col min-h-0 overflow-hidden">
                    {{-- Panel header with tab switcher --}}
                    <div class="shrink-0 flex items-center gap-0.5 px-3 py-2 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                        <button type="button"
                                @click="rightPanelMode = 'preview'"
                                class="flex-1 py-1.5 rounded text-xs font-medium transition-colors"
                                :class="rightPanelMode === 'preview'
                                    ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 shadow-sm'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                            {{ __('common.form_builder_inline_preview') }}
                        </button>
                        <button type="button"
                                @click="rightPanelMode = 'properties'"
                                class="flex-1 py-1.5 rounded text-xs font-medium transition-colors relative"
                                :class="rightPanelMode === 'properties'
                                    ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 shadow-sm'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                            {{ __('common.field_properties') ?? 'Properties' }}
                            <span x-show="selectedFieldIdx !== null"
                                  class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-blue-500"></span>
                        </button>
                    </div>

                    {{-- Preview mode --}}
                    <div x-show="rightPanelMode === 'preview'" class="flex-1 min-h-0 overflow-y-auto">
                        <div class="shrink-0 flex items-center justify-between px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                            <span class="text-xs text-slate-400 dark:text-slate-500"
                                  x-text="layoutColumns + ' {{ __('common.form_layout_cols_unit') }}'"></span>
                        </div>
                        @include('settings.document-forms._form-preview-body')
                    </div>

                    {{-- Properties mode --}}
                    <div x-show="rightPanelMode === 'properties'" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                        {{-- Empty state --}}
                        <div x-show="selectedFieldIdx === null" class="flex-1 flex flex-col items-center justify-center gap-3 p-8 text-center">
                            <svg class="w-10 h-10 text-slate-200 dark:text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('common.field_select_to_edit') ?? 'คลิก field เพื่อแก้ไข' }}</p>
                            <p class="text-xs text-slate-300 dark:text-slate-600">{{ __('common.field_select_to_edit_hint') ?? 'หรือลาก field จากแผงซ้าย' }}</p>
                        </div>

                        {{-- Field editor --}}
                        <template x-if="selectedFieldIdx !== null">
                            <div class="flex flex-col flex-1 min-h-0 overflow-hidden">
                                {{-- Editor header --}}
                                <div class="shrink-0 px-4 pt-3 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-400 font-mono shrink-0" x-text="`#${selectedFieldIdx + 1}`"></span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 shrink-0"
                                              x-text="fields[selectedFieldIdx].field_type"></span>
                                        <span class="flex-1 text-sm font-semibold text-slate-700 dark:text-slate-200 truncate"
                                              x-text="fields[selectedFieldIdx].label_th || fields[selectedFieldIdx].label_en || '(ยังไม่ตั้งชื่อ)'"></span>
                                        <button type="button" @click="deselectField()"
                                                class="shrink-0 p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                                title="{{ __('common.close') ?? 'ปิด' }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    {{-- Sub-tabs --}}
                                    <div class="flex gap-0 border-b border-slate-200 dark:border-slate-700 -mx-4 px-4">
                                        <button type="button" @click="configTab = 'basic'"
                                                class="px-3 py-1.5 text-xs font-medium border-b-2 -mb-px transition-colors"
                                                :class="configTab === 'basic'
                                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                                            {{ __('common.field_tab_basic') ?? 'Basic' }}
                                        </button>
                                        <button type="button" @click="configTab = 'rules'"
                                                class="px-3 py-1.5 text-xs font-medium border-b-2 -mb-px transition-colors"
                                                :class="configTab === 'rules'
                                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                                            {{ __('common.field_tab_rules') ?? 'Rules' }}
                                        </button>
                                        <button type="button" @click="configTab = 'perms'"
                                                class="px-3 py-1.5 text-xs font-medium border-b-2 -mb-px transition-colors"
                                                :class="configTab === 'perms'
                                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">
                                            {{ __('common.field_tab_perms') ?? 'Perms' }}
                                        </button>
                                    </div>
                                </div>

                                {{-- Scrollable tab content --}}
                                <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3">

                                    {{-- ===== BASIC TAB ===== --}}
                                    <div x-show="configTab === 'basic'" class="space-y-3">
                                        <div x-show="fields[selectedFieldIdx].field_type !== 'section'">
                                            <label class="text-xs text-slate-500">{{ __('common.document_form_field_key') }}</label>
                                            <input x-model="fields[selectedFieldIdx].field_key"
                                                   class="form-input mt-1 w-full font-mono text-sm" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500"
                                                   x-text="fields[selectedFieldIdx].field_type === 'section' ? '{{ __('common.document_form_section_title') }} (TH)' : '{{ __('common.document_form_field_label') }} (TH)'"></label>
                                            <input x-model="fields[selectedFieldIdx].label_th" class="form-input mt-1 w-full" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500"
                                                   x-text="fields[selectedFieldIdx].field_type === 'section' ? '{{ __('common.document_form_section_title') }} (EN)' : '{{ __('common.document_form_field_label') }} (EN)'"></label>
                                            <input x-model="fields[selectedFieldIdx].label_en" class="form-input mt-1 w-full" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-slate-500">{{ __('common.document_form_field_type') }}</label>
                                            <select x-model="fields[selectedFieldIdx].field_type"
                                                    @change="if(fields[selectedFieldIdx].field_type !== 'lookup') { fields[selectedFieldIdx].lookup_source=''; fields[selectedFieldIdx].depends_on=''; fields[selectedFieldIdx].foreign_key=''; }"
                                                    class="form-input mt-1 w-full">
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
                                        {{-- Placeholder --}}
                                        <div x-show="!['lookup','table','section','image','group','page_break','qr_code','formula'].includes(fields[selectedFieldIdx].field_type)">
                                            <label class="text-xs text-slate-500">{{ __('common.document_form_placeholder') }}</label>
                                            <input x-model="fields[selectedFieldIdx].placeholder" class="form-input mt-1 w-full" />
                                        </div>
                                        {{-- Options: select/radio/checkbox --}}
                                        <div x-show="['select','radio','checkbox'].includes(fields[selectedFieldIdx].field_type)">
                                            <label class="text-xs text-slate-500">{{ __('common.document_form_options_hint') }}</label>
                                            <textarea x-model="fields[selectedFieldIdx].options_raw" rows="4" class="form-input mt-1 w-full resize-y text-sm"></textarea>
                                        </div>
                                        {{-- multi_select --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'multi_select'">
                                            <div class="space-y-2 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_type_multi_select') }}</p>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.document_form_multi_select_source') }}</label>
                                                    <select x-model="fields[selectedFieldIdx].lookup_source"
                                                            x-init="$nextTick(() => { if (fields[selectedFieldIdx].lookup_source) $el.value = fields[selectedFieldIdx].lookup_source })"
                                                            class="form-input mt-1 w-full">
                                                        <option value="">{{ __('common.document_form_multi_select_custom') }}</option>
                                                        <template x-for="[key, src] in Object.entries(lookupSources)" :key="'ms-'+key">
                                                            <template x-if="src.source_type === 'db' || (src.source_type || '') === ''">
                                                                <option :value="key" x-text="(src.label_{{ app()->getLocale() }} || src.label_en || key)"></option>
                                                            </template>
                                                        </template>
                                                    </select>
                                                    <p class="text-xs text-slate-400 mt-1">{{ __('common.document_form_multi_select_source_help') }}</p>
                                                </div>
                                                <div x-show="!fields[selectedFieldIdx].lookup_source">
                                                    <label class="text-xs text-slate-500">{{ __('common.document_form_options_hint') }}</label>
                                                    <textarea x-model="fields[selectedFieldIdx].options_raw" rows="4" class="form-input w-full resize-y text-sm"></textarea>
                                                </div>
                                            </div>
                                        </template>
                                        {{-- Lookup --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'lookup'">
                                            <div class="space-y-2 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_type_lookup') }}</p>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.document_form_lookup_source') }}</label>
                                                    <select x-model="fields[selectedFieldIdx].lookup_source"
                                                            @change="autoSuggestForeignKey(fields[selectedFieldIdx])"
                                                            x-init="$nextTick(() => { if (fields[selectedFieldIdx].lookup_source) $el.value = fields[selectedFieldIdx].lookup_source })"
                                                            class="form-input mt-1 w-full">
                                                        <option value="">{{ __('common.please_select') }}</option>
                                                        <template x-for="[key, src] in Object.entries(lookupSources)" :key="key">
                                                            <option :value="key" x-text="src.label_{{ app()->getLocale() }} || src.label_en"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.document_form_depends_on') }}</label>
                                                    <select x-model="fields[selectedFieldIdx].depends_on"
                                                            x-init="$nextTick(() => { if (fields[selectedFieldIdx].depends_on) $el.value = fields[selectedFieldIdx].depends_on; })"
                                                            @change="autoSuggestForeignKey(fields[selectedFieldIdx])" class="form-input mt-1 w-full">
                                                        <option value="">{{ __('common.none') }}</option>
                                                        <template x-for="(other, oi) in fields" :key="'dep-'+oi">
                                                            <template x-if="oi !== selectedFieldIdx && other.field_type === 'lookup' && other.field_key">
                                                                <option :value="other.field_key" x-text="other.label_th || other.label || other.field_key"></option>
                                                            </template>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.document_form_foreign_key') }}</label>
                                                    <input x-model="fields[selectedFieldIdx].foreign_key" placeholder="e.g. company_id" class="form-input mt-1 w-full" />
                                                </div>
                                            </div>
                                        </template>
                                        {{-- Table columns --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'table'">
                                            <div class="space-y-3 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_table_columns') }}</p>
                                                    <button type="button" @click="addTableColumn(fields[selectedFieldIdx])" class="px-2 py-1 rounded bg-blue-600 text-white text-xs">+ {{ __('common.document_form_table_add_column') }}</button>
                                                </div>
                                                <template x-for="(col, ci) in fields[selectedFieldIdx].table_columns" :key="ci">
                                                    <div class="space-y-1.5 border-l-2 border-slate-200 dark:border-slate-700 pl-2 pb-1">
                                                        <div class="grid grid-cols-2 gap-1.5">
                                                            <input x-model="col.key" placeholder="key" class="form-input py-1 text-xs font-mono" />
                                                            <input x-model="col.label" placeholder="{{ __('common.document_form_field_label') }}" class="form-input py-1 text-xs" />
                                                        </div>
                                                        <div class="flex items-center gap-1.5">
                                                            <select x-model="col.type" class="form-input py-1 text-xs flex-1">
                                                                <option value="text">text</option>
                                                                <option value="number">number</option>
                                                                <option value="select">select</option>
                                                                <option value="checkbox">checkbox</option>
                                                                <option value="date">date</option>
                                                                <option value="lookup">lookup</option>
                                                            </select>
                                                            <button type="button" @click="fields[selectedFieldIdx].table_columns.splice(ci, 1)" class="px-2 py-1 rounded bg-red-600 text-white text-xs shrink-0">{{ __('common.delete') }}</button>
                                                        </div>
                                                        <div x-show="col.type === 'lookup'" class="space-y-1">
                                                            <select x-model="col.lookup_source" @change="autoSuggestTableColumnForeignKey(fields[selectedFieldIdx], col)" class="form-input py-1 text-xs w-full">
                                                                <option value="">{{ __('common.please_select') }}</option>
                                                                <template x-for="[key, src] in Object.entries(lookupSources)" :key="key">
                                                                    <option :value="key" x-text="src.label_{{ app()->getLocale() }} || src.label_en"></option>
                                                                </template>
                                                            </select>
                                                            <select x-model="col.depends_on" @change="autoSuggestTableColumnForeignKey(fields[selectedFieldIdx], col)" class="form-input py-1 text-xs w-full">
                                                                <option value="">— {{ __('common.depends_on') }}</option>
                                                                <template x-for="other in fields[selectedFieldIdx].table_columns.filter(c => c !== col && c.type === 'lookup' && c.key)" :key="other.key">
                                                                    <option :value="other.key" x-text="(other.label || other.key)"></option>
                                                                </template>
                                                            </select>
                                                            <input x-show="col.depends_on" x-model="col.foreign_key" placeholder="foreign_key" class="form-input py-1 text-xs w-full" />
                                                        </div>
                                                        <div x-show="col.type === 'number' || col.type === 'text'">
                                                            <input x-model="col.formula" placeholder="{{ __('common.document_form_column_formula') }}" class="form-input py-1 text-xs font-mono w-full" />
                                                            <p class="text-xs text-slate-400 mt-0.5">{{ __('common.document_form_column_formula_hint') }}</p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        {{-- Group --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'group'">
                                            <div class="space-y-3 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_type_group') }}</p>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="text-xs text-slate-400">{{ __('common.group_min_rows') }}</label>
                                                        <input type="number" min="0" x-model.number="fields[selectedFieldIdx].group_options.min_rows" class="form-input py-1 text-xs mt-1 w-full">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-slate-400">{{ __('common.group_max_rows') }}</label>
                                                        <input type="number" min="1" max="200" x-model.number="fields[selectedFieldIdx].group_options.max_rows" class="form-input py-1 text-xs mt-1 w-full">
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-slate-400">{{ __('common.group_layout_columns') }}</label>
                                                        <select x-model.number="fields[selectedFieldIdx].group_options.layout_columns" class="form-input py-1 text-xs mt-1 w-full">
                                                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-slate-400">{{ __('common.group_label_singular') }}</label>
                                                        <input type="text" x-model="fields[selectedFieldIdx].group_options.label_singular" placeholder="{{ __('common.group_row_default') }}" class="form-input py-1 text-xs mt-1 w-full">
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-xs font-medium text-slate-500">{{ __('common.group_inner_fields') }}</p>
                                                    <button type="button" @click="addGroupInnerField(fields[selectedFieldIdx])" class="px-2 py-1 rounded bg-blue-600 text-white text-xs">+ {{ __('common.group_add_inner_field') }}</button>
                                                </div>
                                                <template x-for="(inner, ii) in (fields[selectedFieldIdx].group_options.fields || [])" :key="ii">
                                                    <div class="border-l-2 border-slate-200 dark:border-slate-700 pl-2 space-y-1">
                                                        <div class="grid grid-cols-2 gap-1">
                                                            <input x-model="inner.key" placeholder="key" class="form-input py-1 text-xs font-mono" />
                                                            <input x-model="inner.label_th" placeholder="Label TH" class="form-input py-1 text-xs" />
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <select x-model="inner.type" class="form-input py-1 text-xs flex-1">
                                                                <option value="text">text</option>
                                                                <option value="textarea">textarea</option>
                                                                <option value="number">number</option>
                                                                <option value="currency">currency</option>
                                                                <option value="date">date</option>
                                                                <option value="select">select</option>
                                                                <option value="checkbox">checkbox</option>
                                                                <option value="lookup">lookup</option>
                                                            </select>
                                                            <label class="inline-flex items-center gap-1 text-xs whitespace-nowrap shrink-0">
                                                                <input type="checkbox" x-model="inner.required"> {{ __('common.document_form_required') }}
                                                            </label>
                                                            <button type="button" @click="fields[selectedFieldIdx].group_options.fields.splice(ii, 1)" class="px-1.5 py-1 rounded bg-red-600 text-white text-xs shrink-0">&times;</button>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                        {{-- QR Code --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'qr_code'">
                                            <div class="space-y-3 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_type_qr_code') }}</p>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.qr_template') }}</label>
                                                    <input type="text" x-model="fields[selectedFieldIdx].qr_options.template"
                                                           placeholder="https://example.com/verify/{ref_no}"
                                                           maxlength="1000"
                                                           class="form-input mt-1 text-xs font-mono w-full">
                                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('common.qr_token_help') }}</p>
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="text-xs text-slate-500">{{ __('common.qr_size') }}</label>
                                                        <select x-model.number="fields[selectedFieldIdx].qr_options.size" class="form-input mt-1 text-xs w-full">
                                                            <option value="96">96 px</option>
                                                            <option value="128">128 px</option>
                                                            <option value="192">192 px</option>
                                                            <option value="256">256 px</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-slate-500">{{ __('common.qr_label_position') }}</label>
                                                        <select x-model="fields[selectedFieldIdx].qr_options.label_position" class="form-input mt-1 text-xs w-full">
                                                            <option value="above">{{ __('common.qr_label_above') }}</option>
                                                            <option value="below">{{ __('common.qr_label_below') }}</option>
                                                            <option value="none">{{ __('common.qr_label_none') }}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        {{-- Formula --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'formula'">
                                            <div class="space-y-3 rounded-lg border border-slate-100 dark:border-slate-700 p-3 bg-slate-50 dark:bg-slate-800/40">
                                                <p class="text-xs font-medium text-slate-500">{{ __('common.document_form_type_formula') }}</p>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.formula_expression_label') }}</label>
                                                    <input type="text" x-model="fields[selectedFieldIdx].expression"
                                                           placeholder="score_a + score_b + score_c"
                                                           maxlength="500"
                                                           class="form-input mt-1 text-xs font-mono w-full">
                                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('common.formula_help') }}</p>
                                                </div>
                                                <div>
                                                    <label class="text-xs text-slate-500">{{ __('common.formula_decimals') }}</label>
                                                    <input type="number" min="0" max="8" x-model.number="fields[selectedFieldIdx].decimals" class="form-input mt-1 text-xs w-24">
                                                </div>
                                            </div>
                                        </template>
                                        {{-- Toggles + col_span --}}
                                        <div x-show="fields[selectedFieldIdx].field_type !== 'section'" class="border-t border-slate-100 dark:border-slate-700 pt-3 space-y-2">
                                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" x-model="fields[selectedFieldIdx].is_required">
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.document_form_required') }}</span>
                                                </label>
                                                <label class="inline-flex items-center gap-2" x-show="isSearchableType(fields[selectedFieldIdx].field_type)">
                                                    <input type="checkbox" x-model="fields[selectedFieldIdx].is_searchable">
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.document_form_searchable') }}</span>
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" x-model="fields[selectedFieldIdx].is_readonly">
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ __('common.field_readonly') }}</span>
                                                </label>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-slate-500">{{ __('common.document_form_col_span') }}</span>
                                                <select x-model.number="fields[selectedFieldIdx].col_span" class="form-input py-1 px-2 text-xs">
                                                    <option value="0">{{ __('common.document_form_col_span_auto') }}</option>
                                                    <option value="1">1</option>
                                                    <option value="2">2</option>
                                                    <option value="3">3</option>
                                                    <option value="4">4</option>
                                                </select>
                                            </div>
                                        </div>
                                        {{-- Default value (conditional by type) --}}
                                        <template x-if="fields[selectedFieldIdx].field_type === 'date'">
                                            <div>
                                                <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                                <input type="text" x-model="fields[selectedFieldIdx].default_value" placeholder="today / 2026-01-01" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('common.date_expression_help') }}</p>
                                            </div>
                                        </template>
                                        <template x-if="['text','textarea','email','phone'].includes(fields[selectedFieldIdx].field_type)">
                                            <div>
                                                <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                                <input type="text" x-model="fields[selectedFieldIdx].default_value" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                            </div>
                                        </template>
                                        <template x-if="['number','currency'].includes(fields[selectedFieldIdx].field_type)">
                                            <div>
                                                <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                                <input type="number" step="0.01" x-model="fields[selectedFieldIdx].default_value" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                            </div>
                                        </template>
                                        <template x-if="['select','radio'].includes(fields[selectedFieldIdx].field_type)">
                                            <div>
                                                <label class="text-xs text-slate-400">{{ __('common.default_value') }}</label>
                                                <select x-model="fields[selectedFieldIdx].default_value" class="form-input py-1 px-2 text-xs mt-1 w-full">
                                                    <option value="">—</option>
                                                    <template x-for="opt in (fields[selectedFieldIdx].options_raw || '').split('\n').map(s => s.trim()).filter(Boolean)" :key="opt">
                                                        <option :value="opt" x-text="opt"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </template>
                                    </div>{{-- /basic tab --}}

                                    {{-- ===== RULES TAB ===== --}}
                                    <div x-show="configTab === 'rules'" class="space-y-4">
                                        {{-- Visibility Rules --}}
                                        <div>
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.visibility_rules') ?? 'Visibility Rules' }}</p>
                                            <template x-for="(rule, ri) in (fields[selectedFieldIdx].visibility_rules || [])" :key="ri">
                                                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1.5fr)_auto] items-center gap-1.5 mb-2">
                                                    <select x-model="rule.field"
                                                            x-init="$nextTick(() => { if (rule.field) $el.value = rule.field })"
                                                            class="form-input py-1 px-2 text-xs min-w-0">
                                                        <option value="">{{ __('common.select_field') }}</option>
                                                        <template x-for="(other, oi) in fields" :key="'vis-'+oi">
                                                            <template x-if="oi !== selectedFieldIdx && other.field_key && other.field_type !== 'section'">
                                                                <option :value="other.field_key" x-text="other.label_th || other.label || other.field_key"></option>
                                                            </template>
                                                        </template>
                                                    </select>
                                                    <select x-model="rule.operator" class="form-input py-1 px-1 text-xs w-20 text-center">
                                                        <option value="equals">{{ __('common.op_equals') }}</option>
                                                        <option value="not_equals">{{ __('common.op_not_equals') }}</option>
                                                        <option value="is_empty">{{ __('common.op_is_empty') }}</option>
                                                        <option value="is_not_empty">{{ __('common.op_is_not_empty') }}</option>
                                                        <option value="greater_than">{{ __('common.op_greater_than') }}</option>
                                                        <option value="less_than">{{ __('common.op_less_than') }}</option>
                                                    </select>
                                                    <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)"
                                                           x-model="rule.value" placeholder="{{ __('common.value') }}"
                                                           class="form-input py-1 px-2 text-xs min-w-0" />
                                                    <button type="button" @click="fields[selectedFieldIdx].visibility_rules.splice(ri, 1)" class="text-red-500 hover:text-red-700 text-base leading-none">&times;</button>
                                                </div>
                                            </template>
                                            <button type="button"
                                                    @click="if(!fields[selectedFieldIdx].visibility_rules) fields[selectedFieldIdx].visibility_rules = []; fields[selectedFieldIdx].visibility_rules.push({field:'', operator:'equals', value:''})"
                                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('common.add_condition') ?? 'Add condition' }}</button>
                                        </div>
                                        {{-- Required Rules --}}
                                        <div x-show="!fields[selectedFieldIdx].is_required">
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.required_rules') }}</p>
                                            <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">{{ __('common.required_rules_help') }}</p>
                                            <template x-for="(rule, ri) in (fields[selectedFieldIdx].required_rules || [])" :key="'req-'+ri">
                                                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1.5fr)_auto] items-center gap-1.5 mb-2">
                                                    <select x-model="rule.field"
                                                            x-init="$nextTick(() => { if (rule.field) $el.value = rule.field })"
                                                            class="form-input py-1 px-2 text-xs min-w-0">
                                                        <option value="">{{ __('common.select_field') }}</option>
                                                        <template x-for="(other, oi) in fields" :key="'req-fld-'+oi">
                                                            <template x-if="oi !== selectedFieldIdx && other.field_key && other.field_type !== 'section'">
                                                                <option :value="other.field_key" x-text="other.label_th || other.label || other.field_key"></option>
                                                            </template>
                                                        </template>
                                                    </select>
                                                    <select x-model="rule.operator" class="form-input py-1 px-1 text-xs w-20 text-center">
                                                        <option value="equals">{{ __('common.op_equals') }}</option>
                                                        <option value="not_equals">{{ __('common.op_not_equals') }}</option>
                                                        <option value="is_empty">{{ __('common.op_is_empty') }}</option>
                                                        <option value="is_not_empty">{{ __('common.op_is_not_empty') }}</option>
                                                        <option value="greater_than">{{ __('common.op_greater_than') }}</option>
                                                        <option value="less_than">{{ __('common.op_less_than') }}</option>
                                                    </select>
                                                    <input x-show="!['is_empty','is_not_empty'].includes(rule.operator)"
                                                           x-model="rule.value" placeholder="{{ __('common.value') }}"
                                                           class="form-input py-1 px-2 text-xs min-w-0" />
                                                    <button type="button" @click="fields[selectedFieldIdx].required_rules.splice(ri, 1)" class="text-red-500 hover:text-red-700 text-base leading-none">&times;</button>
                                                </div>
                                            </template>
                                            <button type="button"
                                                    @click="if(!fields[selectedFieldIdx].required_rules) fields[selectedFieldIdx].required_rules = []; fields[selectedFieldIdx].required_rules.push({field:'', operator:'equals', value:''})"
                                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('common.add_condition') ?? 'Add condition' }}</button>
                                        </div>
                                        {{-- Validation Rules --}}
                                        <div>
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.validation_rules') ?? 'Validation Rules' }}</p>
                                            <div class="space-y-2">
                                                <div x-show="['text','textarea','email','phone'].includes(fields[selectedFieldIdx].field_type)">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.min_length') ?? 'Min length' }}</label>
                                                            <input type="number" min="0" x-model.number="fields[selectedFieldIdx].validation_rules.min_length" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.max_length') ?? 'Max length' }}</label>
                                                            <input type="number" min="0" x-model.number="fields[selectedFieldIdx].validation_rules.max_length" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="['text','email','phone'].includes(fields[selectedFieldIdx].field_type)">
                                                    <label class="text-xs text-slate-400">{{ __('common.regex_pattern') ?? 'Regex pattern' }}</label>
                                                    <input type="text" x-model="fields[selectedFieldIdx].validation_rules.regex" placeholder="^[A-Z].*" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                </div>
                                                <div x-show="['number','currency'].includes(fields[selectedFieldIdx].field_type)">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.min_value') ?? 'Min value' }}</label>
                                                            <input type="number" step="0.01" x-model.number="fields[selectedFieldIdx].validation_rules.min" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.max_value') ?? 'Max value' }}</label>
                                                            <input type="number" step="0.01" x-model.number="fields[selectedFieldIdx].validation_rules.max" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div x-show="fields[selectedFieldIdx].field_type === 'date'">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.min_date') }}</label>
                                                            <input type="text" x-model="fields[selectedFieldIdx].validation_rules.min_date" placeholder="today / 2026-01-01" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-slate-400">{{ __('common.max_date') }}</label>
                                                            <input type="text" x-model="fields[selectedFieldIdx].validation_rules.max_date" placeholder="today / 2026-12-31" class="form-input py-1 px-2 text-xs mt-1 w-full" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Required at step --}}
                                        <div x-show="stepRolesForRequiredAt(fields[selectedFieldIdx]).length > 0">
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.required_at_step') }}</p>
                                            <p class="text-xs text-slate-400 dark:text-slate-500 mb-2">{{ __('common.required_at_step_hint') }}</p>
                                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                                <template x-for="role in stepRolesForRequiredAt(fields[selectedFieldIdx])" :key="role.value">
                                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                                        <input type="checkbox"
                                                               :checked="isRequiredAtStep(fields[selectedFieldIdx], role.value)"
                                                               @change="toggleRequiredAtStep(fields[selectedFieldIdx], role.value)"
                                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                                        <span x-text="role.label"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </div>
                                    </div>{{-- /rules tab --}}

                                    {{-- ===== PERMS TAB ===== --}}
                                    <div x-show="configTab === 'perms'" class="space-y-4">
                                        {{-- editable_by --}}
                                        <div>
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.field_editable_by') }}</p>
                                            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                                <template x-for="role in availableRoles" :key="role.value">
                                                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                                        <input type="checkbox" :value="role.value"
                                                               :checked="(fields[selectedFieldIdx].editable_by || []).includes(role.value)"
                                                               @change="toggleArrayValue(fields[selectedFieldIdx], 'editable_by', role.value)"
                                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                                        <span x-text="role.label"></span>
                                                    </label>
                                                </template>
                                            </div>
                                            @if(count($companyUsersJs))
                                            <div class="mt-2">
                                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.field_editable_by_users') }}</p>
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <template x-for="u in selectedEditableUsers(fields[selectedFieldIdx])" :key="u.id">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs">
                                                            <span x-text="u.name"></span>
                                                            <button type="button" class="text-blue-500 hover:text-red-600" @click="removeEditableUser(fields[selectedFieldIdx], u.id)">×</button>
                                                        </span>
                                                    </template>
                                                    <button type="button" class="text-xs text-blue-600 hover:underline ml-1" @click="userPickerOpen = !userPickerOpen">
                                                        + {{ __('common.field_editable_by_users_add') }}
                                                    </button>
                                                </div>
                                                <div x-show="userPickerOpen" class="mt-1 p-2 border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-800">
                                                    <input type="text" x-model="userPickerQuery" placeholder="{{ __('common.field_editable_by_users_search') }}"
                                                           class="form-input py-1 px-2 text-xs w-full mb-1" />
                                                    <div class="max-h-36 overflow-y-auto">
                                                        <template x-for="u in availableEditableUsers(fields[selectedFieldIdx], userPickerQuery)" :key="u.id">
                                                            <button type="button"
                                                                    class="block w-full text-left text-xs px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300"
                                                                    @click="addEditableUser(fields[selectedFieldIdx], u.id); userPickerQuery = ''">
                                                                <span x-text="u.name"></span>
                                                            </button>
                                                        </template>
                                                        <p x-show="!availableEditableUsers(fields[selectedFieldIdx], userPickerQuery).length" class="text-xs text-slate-400 dark:text-slate-500 px-2 py-1">
                                                            {{ __('common.field_editable_by_users_empty') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                            <p x-show="!(fields[selectedFieldIdx].editable_by || []).length" class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                                {{ __('common.field_editable_by_none_hint') }}
                                            </p>
                                        </div>
                                        {{-- visible_to_departments --}}
                                        <div>
                                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('common.field_visible_to_departments') }}</p>
                                            @if(count($departmentsJs))
                                                <div class="flex flex-wrap gap-x-4 gap-y-1 max-h-36 overflow-y-auto p-1 rounded border border-slate-200 dark:border-slate-600">
                                                    <template x-for="dept in departments" :key="dept.id">
                                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                                            <input type="checkbox" :value="dept.id"
                                                                   :checked="(fields[selectedFieldIdx].visible_to_departments || []).map(Number).includes(dept.id)"
                                                                   @change="toggleArrayValue(fields[selectedFieldIdx], 'visible_to_departments', dept.id, true)"
                                                                   class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700">
                                                            <span x-text="dept.name"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                                <p x-show="!(fields[selectedFieldIdx].visible_to_departments || []).length" class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                                    {{ __('common.field_visible_to_departments_all_hint') }}
                                                </p>
                                            @else
                                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.field_visible_to_departments_empty') }}</p>
                                            @endif
                                        </div>
                                    </div>{{-- /perms tab --}}

                                </div>{{-- /scrollable tab content --}}
                            </div>
                        </template>{{-- /x-if selectedFieldIdx --}}
                    </div>{{-- /properties mode --}}
                </div>
            </div>{{-- /right panel --}}

        </div>{{-- /grid --}}
    </div>{{-- /fields tab --}}

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
        if (!Array.isArray(f.required_at_step)) f.required_at_step = [];
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
        if (f.field_type === 'qr_code') {
            const q = (typeof f.qr_options === 'object' && f.qr_options !== null) ? f.qr_options : {};
            f.qr_options = {
                template: typeof q.template === 'string' ? q.template : '',
                size: typeof q.size === 'number' ? q.size : 128,
                label_position: typeof q.label_position === 'string' ? q.label_position : 'below',
            };
        }
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
            get runningNumberInfo() {
                return this.runningNumberConfigs[this.currentDocumentType] || null;
            },
            get hasAutoNumberField() {
                return this.fields.some(f => f.field_type === 'auto_number');
            },
            userPickerOpen: false,
            userPickerQuery: '',
            currentDocumentType: initialDocumentType || '',
            roleLabels: roleLabels || { requester: 'Requester', step_prefix: 'Step' },
            layoutColumns: 1,
            // Config panel state
            selectedFieldIdx: null,
            rightPanelMode: 'preview',  // 'preview' | 'properties'
            configTab: 'basic',          // 'basic' | 'rules' | 'perms'
            pageTab: 'settings',         // 'settings' | 'fields'
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
            stepRolesForRequiredAt(field) {
                return this.availableRoles.filter(r =>
                    r.value.startsWith('step_') && (field.editable_by || []).includes(r.value)
                );
            },
            toggleRequiredAtStep(field, stepToken) {
                const stepNo = parseInt(stepToken.replace('step_', ''), 10);
                if (!Array.isArray(field.required_at_step)) field.required_at_step = [];
                const idx = field.required_at_step.indexOf(stepNo);
                if (idx === -1) field.required_at_step.push(stepNo);
                else field.required_at_step.splice(idx, 1);
            },
            isRequiredAtStep(field, stepToken) {
                const stepNo = parseInt(stepToken.replace('step_', ''), 10);
                return (field.required_at_step || []).includes(stepNo);
            },
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
            showInlinePreview: false,
            showSaveConfirm: false,
            previewTitle: '',
            // Select a field card for editing in the right panel
            selectField(idx) {
                this.selectedFieldIdx = idx;
                this.rightPanelMode = 'properties';
                this.userPickerOpen = false;
                this.userPickerQuery = '';
            },
            deselectField() {
                this.selectedFieldIdx = null;
                this.rightPanelMode = 'preview';
                this.userPickerOpen = false;
                this.userPickerQuery = '';
            },
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

                root.querySelectorAll('[data-palette-group]').forEach(group => {
                    if (group.dataset.sortableInit) return;
                    group.dataset.sortableInit = '1';
                    Sortable.create(group, {
                        group: { name: 'form-fields', pull: 'clone', put: false },
                        sort: false,
                        animation: 150,
                    });
                });

                const canvas = root.querySelector('[data-form-canvas]');
                if (canvas && !canvas.dataset.sortableInit) {
                    canvas.dataset.sortableInit = '1';
                    Sortable.create(canvas, {
                        group: { name: 'form-fields', pull: true, put: true },
                        animation: 150,
                        handle: '.drag-handle',
                        ghostClass: 'opacity-30',
                        onAdd: (evt) => {
                            const node = evt.item;
                            const type = node.getAttribute('data-field-type');
                            const insertAt = evt.newIndex;
                            node.remove();
                            if (type) this.addField(type, insertAt);
                        },
                        onUpdate: (evt) => {
                            const { oldIndex, newIndex } = evt;
                            if (oldIndex === newIndex || oldIndex == null || newIndex == null) return;
                            const arr = this.fields;
                            const [moved] = arr.splice(oldIndex, 1);
                            arr.splice(newIndex, 0, moved);
                            // Keep selectedFieldIdx pointing to the same field after reorder
                            if (this.selectedFieldIdx !== null) {
                                if (this.selectedFieldIdx === oldIndex) {
                                    this.selectedFieldIdx = newIndex;
                                } else if (oldIndex < newIndex) {
                                    if (this.selectedFieldIdx > oldIndex && this.selectedFieldIdx <= newIndex) {
                                        this.selectedFieldIdx--;
                                    }
                                } else {
                                    if (this.selectedFieldIdx >= newIndex && this.selectedFieldIdx < oldIndex) {
                                        this.selectedFieldIdx++;
                                    }
                                }
                            }
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
                    this.selectedFieldIdx = this.fields.length - 1;
                } else {
                    this.fields.splice(insertAt, 0, row);
                    this.selectedFieldIdx = insertAt;
                }
                this.rightPanelMode = 'properties';
                this.configTab = 'basic';
                this.pageTab = 'fields';
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
                if (this.selectedFieldIdx === idx) {
                    this.selectedFieldIdx = null;
                    this.rightPanelMode = 'preview';
                } else if (this.selectedFieldIdx !== null && this.selectedFieldIdx > idx) {
                    this.selectedFieldIdx--;
                }
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
