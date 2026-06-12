@extends($layout ?? 'layouts.app')

@section('title', $form->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => __('common.fill_form')],
    ]" />
@endsection

@section('content')
{{-- Holiday calendar for live WORKDAYS() evaluation (server recomputes on save) --}}
<script>window.__HOLIDAYS__ = @json(app(\App\Support\WorkdayCalculator::class)->activeDates());</script>
<div style="width:100%;max-width:100%">
    <div class="mb-6">
        <a href="{{ route('forms.index') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ $form->name }}</h2>
        @if($form->description)
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $form->description }}</p>
        @endif
    </div>

    @if($errors->any())
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $resolveFieldDefault = function ($field) {
            if (! $field->default_value) {
                return '';
            }
            if ($field->field_type === 'date') {
                return \App\Support\DateExpressionResolver::resolve($field->default_value) ?? '';
            }
            return (string) $field->default_value;
        };

        $initialPayload = collect($form->fields)
            ->filter(fn($f) => !in_array($f->field_type, ['section', 'auto_number']))
            ->mapWithKeys(fn($f) => [$f->field_key => old("fields.{$f->field_key}", $resolveFieldDefault($f))])
            ->all();
    @endphp
    <form method="POST" action="{{ route('forms.draft.store', $form->form_key) }}" enctype="multipart/form-data" novalidate class="w-full"
          x-data="dynamicForm({{ json_encode($initialPayload, JSON_UNESCAPED_UNICODE) }})">
        @csrf

        @if(($onBehalfUsers ?? collect())->isNotEmpty())
            {{-- Submit on behalf of — permission-gated (submission.create_for_others) --}}
            <div class="card p-4 sm:p-6 mb-4 border border-amber-200 dark:border-amber-700/50 bg-amber-50/40 dark:bg-amber-900/10">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('common.on_behalf_of') }}
                    <span class="text-xs font-normal text-slate-400">({{ __('common.optional') }})</span>
                </label>
                <select name="on_behalf_of_user_id" class="form-input mt-1 max-w-md">
                    <option value="">{{ __('common.on_behalf_self') }}</option>
                    @foreach($onBehalfUsers as $u)
                        <option value="{{ $u->id }}" @selected(old('on_behalf_of_user_id') == $u->id)>
                            {{ trim($u->first_name.' '.$u->last_name) }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.on_behalf_hint') }}</p>
            </div>
        @endif

        <div class="card p-4 sm:p-6 lg:p-8">
            <x-document-form-fields-grid :columns="$form->layout_columns ?? 1" class="gap-x-6 gap-y-5 lg:gap-x-10">
                @foreach($form->fields as $field)
                    @php
                        $fKey   = $field->field_key;
                        $fName  = "fields[{$fKey}]";
                        $fValue = old("fields.{$fKey}", $resolveFieldDefault($field));
                        $fSpan  = ($field->col_span && ($form->layout_columns ?? 1) > 1)
                            ? min($field->col_span, $form->layout_columns)
                            : 1;
                        $fVisRules = $field->visibility_rules ?? [];
                        // Build Alpine inline expression from rules
                        // e.g. [{"field":"priority","operator":"equals","value":"ฉุกเฉิน"}]
                        // → "fp['priority'] === 'ฉุกเฉิน'"
                        $xShowExpr = '';
                        if (!empty($fVisRules)) {
                            $parts = [];
                            foreach ($fVisRules as $rule) {
                                $f = $rule['field'] ?? '';
                                $op = $rule['operator'] ?? 'equals';
                                $v = json_encode($rule['value'] ?? '', JSON_UNESCAPED_UNICODE);
                                $ref = "fp[" . json_encode($f) . "]";
                                $parts[] = match($op) {
                                    'equals' => "(Array.isArray({$ref}) ? {$ref}.includes({$v}) : {$ref} === {$v})",
                                    'not_equals' => "(Array.isArray({$ref}) ? !{$ref}.includes({$v}) : {$ref} !== {$v})",
                                    'is_empty' => "!{$ref} || String({$ref}).trim() === ''",
                                    'is_not_empty' => "!!{$ref} && String({$ref}).trim() !== ''",
                                    'greater_than' => "Number({$ref}) > Number({$v})",
                                    'less_than' => "Number({$ref}) < Number({$v})",
                                    'in' => "{$v}.includes(String({$ref}))",
                                    'not_in' => "!{$v}.includes(String({$ref}))",
                                    default => 'true',
                                };
                            }
                            $xShowExpr = implode(' && ', $parts);
                        }
                    @endphp
                    <div @if($fSpan > 1) style="grid-column: span {{ $fSpan }}" @endif>
                      @if($xShowExpr)
                        <div x-show="{{ $xShowExpr }}" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0">
                      @endif
                        @if($field->field_type !== 'section')
                            <label class="form-label">
                                {{ $field->localized_label }}
                                @if($field->is_required)
                                    <span class="text-red-500">*</span>
                                @elseif(! empty($field->required_rules))
                                    <span x-show="requiredRulesActive(@js($field->required_rules))" x-cloak class="text-red-500">*</span>
                                @endif
                            </label>
                        @endif
                        @include('components.dynamic-field', [
                            'field'        => $field,
                            'name'         => $fName,
                            'value'        => $fValue,
                            'editorRole'   => 'requester',
                            'editorUserId' => (int) (session('user.id') ?? 0) ?: null,
                        ])
                      @if($xShowExpr)
                        </div>
                      @endif
                    </div>
                @endforeach
            </x-document-form-fields-grid>

            @if(($overrideStages ?? collect())->isNotEmpty())
                {{-- Requester-override: pick the approver up-front; the choice is
                     carried through storeDraft's redirect to the submit step. --}}
                <div class="mt-6 rounded-lg border border-blue-200 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-900/10 p-4">
                    <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ __('common.submit_pick_approver_label') }}</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($overrideStages as $overrideStage)
                            <div>
                                <label class="text-xs text-slate-500 dark:text-slate-400">{{ $overrideStage->name }}</label>
                                <select name="picked_approvers[{{ $overrideStage->step_no }}]" class="form-input mt-1 w-full">
                                    <option value="">{{ __('common.submit_pick_approver_use_default') }}</option>
                                    @foreach(($eligibleApprovers ?? collect()) as $appr)
                                        <option value="{{ $appr['id'] }}" @selected(old("picked_approvers.{$overrideStage->step_no}") == $appr['id'])>{{ $appr['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 sm:flex sm:justify-end sm:gap-3" x-data="{ intent: 'draft' }">
                <input type="hidden" name="_intent" :value="intent">
                <button type="submit" class="btn-secondary justify-center w-full sm:w-auto py-3 sm:py-2 text-base sm:text-sm mb-2 sm:mb-0"
                        @click="intent = 'draft'">
                    {{ __('common.save_draft') }}
                </button>
                <button type="button" class="btn-primary justify-center w-full sm:w-auto py-3 sm:py-2 text-base sm:text-sm"
                        @click="intent = 'submit'; window.dispatchEvent(new CustomEvent('confirm-open', {detail:{message:'{{ addslashes(__('common.confirm_submit_form')) }}', okLabel:'{{ addslashes(__('common.submit_form')) }}', danger:false, form:$el.closest('form')}}))">
                    {{ __('common.submit_form') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
