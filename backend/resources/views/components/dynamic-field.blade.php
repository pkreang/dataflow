@php
    $inputClass = 'form-input mt-1';

    // Visibility check (Feature 1B) — department-based (server-side)
    $userDeptId = $userDeptId ?? null;
    $editorRole = $editorRole ?? 'requester';
    $editorUserId = $editorUserId ?? null;
    $visibleDepts = $field->visible_to_departments;
    $isVisible = empty($visibleDepts)
        || ($userDeptId !== null && in_array((int) $userDeptId, array_map('intval', $visibleDepts)));

    // Editability check (Feature 2) — token set may contain role tokens
    // ('requester', 'step_N') and user tokens ('user:{id}').
    $effectiveEditableBy = $field->effective_editable_by; // accessor: null → ['requester']
    $canEditByRole = in_array($editorRole, $effectiveEditableBy, true);
    $canEditByUser = $editorUserId && in_array('user:'.(int) $editorUserId, $effectiveEditableBy, true);
    $isReadOnly = $field->field_type !== 'section'
        && ((! $canEditByRole && ! $canEditByUser) || (bool) ($field->is_readonly ?? false));
    $readonlyClass = $isReadOnly ? ' opacity-70 cursor-not-allowed bg-gray-50 dark:bg-gray-800' : '';

    // Visibility rules (client-side conditional show/hide based on other field values)
    $visibilityRules = $field->visibility_rules ?? [];
    $hasVisibilityRules = !empty($visibilityRules);

    // Validation rules (HTML5 attributes)
    $validationRules = $field->validation_rules ?? [];
    $validationAttrs = '';
    if (!empty($validationRules['min_length'])) $validationAttrs .= ' minlength="' . (int) $validationRules['min_length'] . '"';
    if (!empty($validationRules['max_length'])) $validationAttrs .= ' maxlength="' . (int) $validationRules['max_length'] . '"';
    if (!empty($validationRules['regex']))      $validationAttrs .= ' pattern="' . e($validationRules['regex']) . '"';
    if (isset($validationRules['min']) && in_array($field->field_type, ['number', 'currency']))
        $validationAttrs .= ' min="' . $validationRules['min'] . '"';
    elseif ($field->field_type === 'currency')
        $validationAttrs .= ' min="0"';
    if (isset($validationRules['max']) && in_array($field->field_type, ['number', 'currency']))
        $validationAttrs .= ' max="' . $validationRules['max'] . '"';
    if ($field->field_type === 'date') {
        if (!empty($validationRules['min_date'])) {
            $resolvedMin = \App\Support\DateExpressionResolver::resolve($validationRules['min_date']);
            if ($resolvedMin) $validationAttrs .= ' min="' . $resolvedMin . '"';
        }
        if (!empty($validationRules['max_date'])) {
            $resolvedMax = \App\Support\DateExpressionResolver::resolve($validationRules['max_date']);
            if ($resolvedMax) $validationAttrs .= ' max="' . $resolvedMax . '"';
        }
    }
@endphp

@if($isVisible)

@if($field->field_type === 'auto_number')
    {{-- Document number — always read-only, auto-generated on submit --}}
    @php
        $autoValue = $value ?? $referenceNo ?? null;
    @endphp
    <input type="text" value="{{ $autoValue }}" readonly
           placeholder="Auto Generate"
           class="form-input mt-1 bg-slate-50 dark:bg-slate-800 cursor-not-allowed font-mono {{ $autoValue ? 'font-semibold text-slate-900 dark:text-slate-100' : 'italic text-slate-400 dark:text-slate-500' }}"
    >

@elseif($field->field_type === 'section')
    {{-- Section divider — display only, no input --}}
    <div class="border-b border-gray-300 dark:border-gray-600 pb-1 mt-2">
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $field->localized_label }}</h4>
    </div>

@elseif($field->field_type === 'qr_code')
    @php
        $qrOpts = is_array($field->options) ? $field->options : [];
        $qrSize = (int) ($qrOpts['size'] ?? 128);
        $qrLabelPos = (string) ($qrOpts['label_position'] ?? 'below');
        // $qrPayload is supplied by show-submission etc. when the submission
        // exists; null on data-entry views (template tokens haven't resolved
        // yet — show a placeholder instead).
        $qrPayload = $qrPayload ?? null;
    @endphp
    @if(! $qrPayload)
        <div class="text-xs text-slate-400 dark:text-slate-500 italic mt-1">
            {{ __('common.qr_pending_after_submit') }}
        </div>
    @else
        <div class="qr-block inline-flex flex-col items-center gap-1 mt-1">
            @if($qrLabelPos === 'above' && $field->field_type !== 'section')
                <span class="text-xs text-slate-600 dark:text-slate-400">{{ $field->localized_label }}</span>
            @endif
            <canvas data-qr-payload="{{ $qrPayload }}"
                    data-qr-size="{{ $qrSize }}"
                    width="{{ $qrSize }}" height="{{ $qrSize }}"
                    class="border border-slate-200 dark:border-slate-700"></canvas>
            @if($qrLabelPos === 'below' && $field->field_type !== 'section')
                <span class="text-xs text-slate-600 dark:text-slate-400">{{ $field->localized_label }}</span>
            @endif
        </div>
    @endif

@elseif($field->field_type === 'page_break')
    {{-- Page break — visual hint only on screen; print view emits a CSS page-break-after marker --}}
    <div class="my-2 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500" aria-hidden="true">
        <span class="flex-1 border-t border-dashed border-slate-300 dark:border-slate-600"></span>
        <span class="uppercase tracking-wide">{{ __('common.page_break_marker') }}</span>
        <span class="flex-1 border-t border-dashed border-slate-300 dark:border-slate-600"></span>
    </div>

@elseif($field->field_type === 'formula')
    @php
        $formulaOpts = is_array($field->options) ? $field->options : [];
        $formulaExpression = (string) ($formulaOpts['expression'] ?? '');
        $formulaDecimals = max(0, min(8, (int) ($formulaOpts['decimals'] ?? 2)));
    @endphp
    {{-- Display input: read-only, formatted to the configured decimal count.
         Hidden input mirrors the raw value so it lands in payload on submit.
         Both bind reactively to fp (form-data Alpine scope). --}}
    <input type="text" readonly
           class="form-input mt-1 bg-slate-50 dark:bg-slate-800 cursor-not-allowed font-mono"
           :value="(() => { const _v = window.evaluateFormula({{ json_encode($formulaExpression) }}, fp); return _v === null ? '' : Number(_v).toFixed({{ $formulaDecimals }}); })()">
    <input type="hidden" name="{{ $name }}"
           :value="(() => { const _v = window.evaluateFormula({{ json_encode($formulaExpression) }}, fp); return _v === null ? '' : _v; })()">
    @if($formulaExpression === '')
        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ __('common.formula_expression_empty') }}</p>
    @endif

@elseif($field->field_type === 'group')
    @php
        $groupOpts = is_array($field->options) ? $field->options : [];
        $innerFields = is_array($groupOpts['fields'] ?? null) ? $groupOpts['fields'] : [];
        $groupMin = (int) ($groupOpts['min_rows'] ?? 0);
        $groupMax = (int) ($groupOpts['max_rows'] ?? 20);
        $groupCols = max(1, min(4, (int) ($groupOpts['layout_columns'] ?? 1)));
        $groupSingular = (string) ($groupOpts['label_singular'] ?? __('common.group_row_default'));
        $groupRows = is_array($value) ? array_values($value) : [];
        if (empty($groupRows) && $groupMin > 0) {
            $groupRows = array_fill(0, $groupMin, []);
        }
        if (empty($groupRows)) {
            $groupRows = [[]];
        }
    @endphp
    <div class="mt-[var(--field-label-gap)] space-y-[var(--field-gap)]"
         x-data="groupRepeater({ rows: @js($groupRows), minRows: {{ $groupMin }}, maxRows: {{ $groupMax }} })">
        <template x-for="(row, idx) in rows" :key="idx">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-[var(--card-pad-y)] bg-slate-50/50 dark:bg-slate-800/30">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ $groupSingular }} #<span x-text="idx + 1"></span>
                    </span>
                    @if(! $isReadOnly)
                        <button type="button" class="text-xs text-red-500 hover:underline"
                                :disabled="rows.length <= {{ $groupMin }}"
                                :class="rows.length <= {{ $groupMin }} ? 'opacity-40 cursor-not-allowed' : ''"
                                @click="removeRow(idx)">{{ __('common.delete') }}</button>
                    @endif
                </div>
                <div class="grid gap-[var(--field-gap)]" style="grid-template-columns: repeat({{ $groupCols }}, minmax(0, 1fr))">
                    @foreach($innerFields as $inner)
                        @php
                            $iKey = $inner['key'];
                            $iLabel = $inner['label_th'] ?? $inner['label'] ?? $iKey;
                            $iType = $inner['type'] ?? 'text';
                            $iRequired = (bool) ($inner['required'] ?? false);
                            $iSpan = max(1, min($groupCols, (int) ($inner['col_span'] ?? 0) ?: 1));
                        @endphp
                        <div @if($iSpan > 1) style="grid-column: span {{ $iSpan }}" @endif>
                            <label class="form-label text-xs">
                                {{ $iLabel }}
                                @if($iRequired) <span class="text-red-500">*</span> @endif
                            </label>
                            @if(in_array($iType, ['text','email','phone']))
                                <input type="{{ $iType === 'email' ? 'email' : ($iType === 'phone' ? 'tel' : 'text') }}"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @elseif($iType === 'textarea')
                                <textarea :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                          @if($isReadOnly) readonly @endif
                                          class="form-input mt-1 text-sm" rows="2"
                                          x-text="row['{{ $iKey }}'] || ''"></textarea>
                            @elseif(in_array($iType, ['number','currency']))
                                <input type="number" step="{{ $iType === 'currency' ? '0.01' : 'any' }}"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @elseif($iType === 'date')
                                <input type="date"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @elseif($iType === 'time')
                                <input type="time"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @elseif($iType === 'datetime')
                                <input type="datetime-local"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @elseif(in_array($iType, ['select','radio']))
                                <select :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                        @if($isReadOnly) disabled @endif
                                        class="form-input mt-1 text-sm">
                                    <option value="">—</option>
                                    @foreach((array) ($inner['options'] ?? []) as $opt)
                                        <option value="{{ $opt }}" :selected="(row['{{ $iKey }}'] || '') === '{{ $opt }}'">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($iType === 'multi_select' || $iType === 'checkbox')
                                <div class="mt-[var(--field-label-gap)] space-y-[var(--field-gap)]">
                                    @foreach((array) ($inner['options'] ?? []) as $opt)
                                        <label class="inline-flex items-center gap-2 text-xs mr-3">
                                            <input type="checkbox" value="{{ $opt }}"
                                                   :name="`{{ $name }}[${idx}][{{ $iKey }}][]`"
                                                   :checked="Array.isArray(row['{{ $iKey }}']) && row['{{ $iKey }}'].includes('{{ $opt }}')"
                                                   @if($isReadOnly) disabled @endif
                                                   class="rounded border-slate-300">
                                            <span>{{ $opt }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input type="text"
                                       :name="`{{ $name }}[${idx}][{{ $iKey }}]`"
                                       :value="row['{{ $iKey }}'] || ''"
                                       @if($isReadOnly) readonly @endif
                                       class="form-input mt-1 text-sm">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </template>

        @if(! $isReadOnly)
            <button type="button" class="btn-secondary text-xs"
                    :disabled="rows.length >= {{ $groupMax }}"
                    :class="rows.length >= {{ $groupMax }} ? 'opacity-40 cursor-not-allowed' : ''"
                    @click="addRow()">
                + {{ __('common.group_add_row', ['name' => $groupSingular]) }}
            </button>
        @endif
    </div>

@elseif($field->field_type === 'textarea')
    <textarea
        name="{{ $name }}"
        @required($field->is_required && !$isReadOnly)
        @readonly($isReadOnly)
        placeholder="{{ $field->placeholder }}"
        {!! $validationAttrs !!}
        class="{{ $inputClass }}{{ $readonlyClass }}"
    >{{ $value }}</textarea>

@elseif($field->field_type === 'select')
    <select name="{{ $name }}" @required($field->is_required && !$isReadOnly) @disabled($isReadOnly) class="{{ $inputClass }}{{ $readonlyClass }}">
        <option value="">{{ __('common.please_select') }}</option>
        @foreach(($field->options ?? []) as $option)
            <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
        @endforeach
    </select>

@elseif($field->field_type === 'radio')
    <div class="mt-2 space-y-[var(--field-gap)]">
        @foreach(($field->options ?? []) as $option)
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 {{ $isReadOnly ? 'opacity-70' : '' }}">
                <input type="radio" name="{{ $name }}" value="{{ $option }}" @checked($value === $option) @disabled($isReadOnly) class="border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                {{ $option }}
            </label>
        @endforeach
    </div>

@elseif($field->field_type === 'checkbox')
    @if(!empty($field->options))
        <div class="mt-2 space-y-[var(--field-gap)]">
            @foreach(($field->options ?? []) as $option)
                @php $checked = is_array($value) ? in_array($option, $value) : false; @endphp
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 {{ $isReadOnly ? 'opacity-70' : '' }}">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" @checked($checked) @disabled($isReadOnly) class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    {{ $option }}
                </label>
            @endforeach
        </div>
    @else
        <div class="mt-2">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 {{ $isReadOnly ? 'opacity-70' : '' }}">
                <input type="checkbox" name="{{ $name }}" value="1" @checked($value) @disabled($isReadOnly) class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                {{ $field->localized_label }}
            </label>
        </div>
    @endif

@elseif($field->field_type === 'file')
    @if($isReadOnly)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">
            {{ $value ? basename((string) $value) : '—' }}
        </p>
    @else
        <input type="file" name="{{ $name }}" @required($field->is_required)
               class="mt-1 w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 dark:file:bg-gray-700 file:text-blue-700 dark:file:text-gray-300">
    @endif

@elseif($field->field_type === 'image')
    @if($value && is_string($value))
        <img src="{{ \Illuminate\Support\Facades\Storage::url($value) }}" alt="{{ $field->localized_label }}"
             class="mb-2 max-h-40 rounded border border-gray-200 dark:border-gray-600">
    @endif
    @if(!$isReadOnly)
        <input type="file" name="{{ $name }}" accept="image/*" @required($field->is_required && !$value)
               class="mt-1 w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 dark:file:bg-gray-700 file:text-blue-700 dark:file:text-gray-300">
    @endif

@elseif($field->field_type === 'multi_file')
    @php
        $paths = is_array($value) ? $value : (is_string($value) && $value !== '' ? (json_decode($value, true) ?: []) : []);
    @endphp
    @if(count($paths) > 0)
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 mb-2">
            @foreach($paths as $path)
                @php $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)); $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp','heic','bmp'], true); @endphp
                <a href="{{ \Illuminate\Support\Facades\Storage::url($path) }}" target="_blank" class="block border border-gray-200 dark:border-gray-600 rounded overflow-hidden hover:border-blue-400">
                    @if($isImg)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($path) }}" alt="attachment" class="w-full h-20 object-cover">
                    @else
                        <div class="flex items-center justify-center h-20 bg-slate-50 dark:bg-slate-800 text-xs text-slate-500 p-1 text-center">{{ basename((string) $path) }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
    @if(!$isReadOnly)
        <input type="file" name="{{ $name }}[]" multiple accept="image/*,application/pdf"
               class="mt-1 w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 dark:file:bg-gray-700 file:text-blue-700 dark:file:text-gray-300">
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('common.multi_file_hint') }}</p>
    @endif

@elseif($field->field_type === 'multi_select')
    @php
        $selected = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?: []) : []);
        $selectedStr = array_map('strval', $selected);
        // Support lookup-driven options: $field->options = ['source' => 'lookup_key']
        $opts = $field->options ?? [];
        $lookupSource = (is_array($opts) && isset($opts['source'])) ? $opts['source'] : null;
        if ($lookupSource) {
            $multiItems = \App\Support\LookupRegistry::getItems($lookupSource)
                ->map(fn ($it) => ['value' => $it['value'] ?? '', 'display' => $it['display'] ?? $it['value'] ?? ''])
                ->all();
        } else {
            // Hardcoded flat array — value == display
            $multiItems = collect(is_array($opts) ? $opts : [])
                ->filter(fn ($o) => ! is_array($o))  // skip nested structures
                ->map(fn ($o) => ['value' => (string) $o, 'display' => (string) $o])
                ->all();
        }
    @endphp
    <select multiple name="{{ $name }}[]" @required($field->is_required && !$isReadOnly) @disabled($isReadOnly)
            class="{{ $inputClass }}{{ $readonlyClass }} h-32">
        @foreach($multiItems as $item)
            <option value="{{ $item['value'] }}" @selected(in_array((string) $item['value'], $selectedStr, true))>{{ $item['display'] }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('common.multi_select_hint') }}</p>

@elseif($field->field_type === 'signature')
    @if($isReadOnly)
        @if($value)
            <img src="{{ $value }}" alt="{{ $field->localized_label }}" class="mt-1 max-h-24 border border-gray-200 dark:border-gray-600 rounded-lg">
        @else
            <p class="mt-1 text-sm text-gray-400 italic">—</p>
        @endif
    @else
        @php
            // Pre-fill the requester's saved signature when no value is in flight yet,
            // so users with a profile signature don't have to draw on every form.
            $savedSig = ($value === null || $value === '')
                ? (auth()->check() ? auth()->user()->signature_url : null)
                : null;
        @endphp
        <x-signature-pad :name="$name" :initial-value="$value ?? ''" :saved-data-url="$savedSig" />
    @endif

@elseif(in_array($field->field_type, ['lookup', 'user_lookup', 'equipment_lookup']))
    @php
        $source = match($field->field_type) {
            'user_lookup' => 'user',
            'equipment_lookup' => 'equipment',
            default => $field->options['source'] ?? null,
        };
        $dependsOn = $field->options['depends_on'] ?? null;
        $foreignKey = $field->options['foreign_key'] ?? null;
    @endphp

    @if($dependsOn && $foreignKey)
        {{-- Cascading lookup: load items dynamically via fetch --}}
        <div x-data="cascadingLookup('{{ $source }}', '{{ $dependsOn }}', '{{ $foreignKey }}', '{{ $value }}')" x-init="init()">
            <select name="{{ $name }}" @required($field->is_required && !$isReadOnly) @disabled($isReadOnly) x-model="selected" class="{{ $inputClass }}{{ $readonlyClass }}">
                <option value="">{{ __('common.please_select') }}</option>
                <template x-for="item in items" :key="item.value">
                    <option :value="item.value" x-text="item.display"></option>
                </template>
            </select>
            <p x-show="loading" class="text-xs text-gray-400 mt-1">{{ __('common.loading') }}...</p>
        </div>
    @else
        {{-- Static lookup: pre-load all items --}}
        @php
            $lookupItems = $source ? \App\Support\LookupRegistry::getItems($source) : collect();
        @endphp
        <select name="{{ $name }}" @required($field->is_required && !$isReadOnly) @disabled($isReadOnly) class="{{ $inputClass }}{{ $readonlyClass }}">
            <option value="">{{ __('common.please_select') }}</option>
            @foreach($lookupItems as $item)
                <option value="{{ $item['value'] }}" @selected($value == $item['value'])>{{ $item['display'] }}</option>
            @endforeach
        </select>
    @endif

@elseif($field->field_type === 'table')
    @php
        $columns = $field->options['columns'] ?? [];
        $tableData = is_array($value) ? $value : (json_decode($value ?? '[]', true) ?: []);
    @endphp
    @if(count($columns))
        <div x-data="{
            columns: @js($columns),
            rows: @js(count($tableData) ? $tableData : []),
            addRow() {
                let row = {};
                this.columns.forEach(c => row[c.key] = '');
                this.rows.push(row);
                this.notifyChange();
            },
            removeRow(i) { this.rows.splice(i, 1); this.notifyChange(); },
            notifyChange() {
                window.dispatchEvent(new CustomEvent('documentform:table-changed', {
                    detail: { fieldKey: @js($field->field_key), rows: this.rows }
                }));
            },
            /** Evaluate a simple arithmetic formula like 'qty * unit_price' against row columns.
             *  Whitelisted tokens: column identifiers, numbers, + - * / ( ) . Fallback to 0 on error. */
            computeFormula(formula, row) {
                if (!formula) return 0;
                const tokens = String(formula).match(/[A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|[+\-*/()%]/g) || [];
                if (!tokens.length) return 0;
                const expr = tokens.map(t => /^[A-Za-z_]/.test(t) ? '(' + Number(row[t] || 0) + ')' : t).join('');
                try {
                    const val = Function('\"use strict\"; return (' + expr + ');')();
                    return Number.isFinite(val) ? val : 0;
                } catch (e) { return 0; }
            },
            formatNumber(n) {
                const num = Number(n) || 0;
                return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }" x-init="
            if(!rows.length && !{{ $isReadOnly ? 'true' : 'false' }}) addRow();
            $watch('rows', () => notifyChange(), { deep: true });
        ">
            <input type="hidden" name="{{ $name }}" :value="JSON.stringify(rows)">
            <div class="mt-1 overflow-x-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 w-10">#</th>
                            <template x-for="col in columns" :key="col.key">
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400" x-text="col.label"></th>
                            </template>
                            <th class="px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, ri) in rows" :key="ri">
                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                <td class="px-3 py-2 text-gray-400 text-xs" x-text="ri + 1"></td>
                                <template x-for="col in columns" :key="col.key">
                                    <td class="px-3 py-1">
                                        <template x-if="!col.formula && col.type === 'select'">
                                            <select x-model="row[col.key]" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                <option value=""></option>
                                                <template x-for="opt in (col.options || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt">
                                                    <option :value="opt" x-text="opt"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="!col.formula && col.type === 'checkbox'">
                                            <input type="checkbox" x-model="row[col.key]" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                        </template>
                                        <template x-if="!col.formula && col.type === 'date'">
                                            <input type="date" x-model="row[col.key]" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        </template>
                                        <template x-if="!col.formula && col.type === 'number'">
                                            <input type="number" step="0.01" x-model="row[col.key]" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        </template>
                                        <template x-if="!col.formula && col.type === 'lookup'">
                                            <div x-data="{ items: [], loaded: false, lastFilterKey: '' }"
                                                 x-effect="
                                                    if (!col.lookup_source) return;
                                                    let filterKey = '';
                                                    const params = new URLSearchParams({ source: col.lookup_source });
                                                    if (col.depends_on && col.foreign_key) {
                                                        const parentVal = row[col.depends_on];
                                                        if (!parentVal) {
                                                            items = []; loaded = false; lastFilterKey = '';
                                                            if (row[col.key]) row[col.key] = '';
                                                            return;
                                                        }
                                                        params.append('filters[' + col.foreign_key + ']', parentVal);
                                                        filterKey = col.foreign_key + '=' + parentVal;
                                                    }
                                                    if (lastFilterKey === filterKey && loaded) return;
                                                    lastFilterKey = filterKey;
                                                    fetch('/lookup?' + params.toString(), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                                                        .then(r => r.json())
                                                        .then(j => {
                                                            items = j.data || [];
                                                            loaded = true;
                                                            if (row[col.key] && !items.some(i => String(i.value) === String(row[col.key]))) {
                                                                row[col.key] = '';
                                                            }
                                                        });
                                                 ">
                                                @if($isReadOnly)
                                                    <span class="text-sm text-gray-900 dark:text-gray-100" x-text="(items.find(i => String(i.value) === String(row[col.key]))?.display) || row[col.key] || '—'"></span>
                                                @else
                                                    <select x-model="row[col.key]" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                                        <option value="">{{ __('common.please_select') }}</option>
                                                        <template x-for="item in items" :key="item.value">
                                                            <option :value="item.value" x-text="item.display"></option>
                                                        </template>
                                                    </select>
                                                @endif
                                            </div>
                                        </template>
                                        <template x-if="col.formula" x-effect="row[col.key] = computeFormula(col.formula, row)">
                                            <span class="inline-block w-full px-2 py-1.5 text-sm text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/50 rounded text-right font-mono" x-text="formatNumber(row[col.key])"></span>
                                        </template>
                                        <template x-if="!col.formula && (!col.type || col.type === 'text')">
                                            <input type="text" x-model="row[col.key]" class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                        </template>
                                    </td>
                                </template>
                                <td class="px-3 py-2">
                                    <button type="button" @click="removeRow(ri)" class="text-red-500 hover:text-red-700 text-xs">&times;</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            @if(!$isReadOnly)
            <button type="button" @click="addRow()" class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:underline">+ {{ __('common.document_form_table_add_row') }}</button>
            @endif
        </div>
    @endif

@elseif($field->field_type === 'currency')
    <div class="relative mt-1">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm">฿</span>
        <input type="number" step="0.01"
               name="{{ $name }}"
               value="{{ $value }}"
               placeholder="{{ $field->placeholder ?? '0.00' }}"
               @required($field->is_required && !$isReadOnly)
               @readonly($isReadOnly)
               {!! $validationAttrs !!}
               class="w-full pl-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700{{ $readonlyClass }}">
    </div>

@else
    {{-- text, number, date, time, datetime, email, phone --}}
    @php
        $typeMap = [
            'number' => 'number',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime-local',
            'email' => 'email',
            'phone' => 'tel',
        ];
        $htmlType = $typeMap[$field->field_type] ?? 'text';
    @endphp
    <input
        type="{{ $htmlType }}"
        step="{{ in_array($field->field_type, ['number', 'currency']) ? '0.01' : '' }}"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $field->placeholder }}"
        @required($field->is_required && !$isReadOnly)
        @readonly($isReadOnly)
        {!! $validationAttrs !!}
        class="{{ $inputClass }}{{ $readonlyClass }}"
    >
@endif

@endif {{-- end @if($isVisible) --}}
