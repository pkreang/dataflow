@extends('layouts.app')

@section('title', $submission->form->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => __('common.my_submissions'), 'url' => route('forms.my-submissions')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
<div style="width:100%;max-width:100%">
    <div class="mb-6">
        <a href="{{ route('forms.my-submissions') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.my_submissions') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ $submission->form->name }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            <span class="badge-yellow">{{ __('common.draft') }}</span>
        </p>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

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
        $form = $submission->form;
        $viewerId = (int) (session('user.id') ?? 0);
        // Only the owner gets the implicit 'requester' role token. Assigned
        // editors edit fields purely through their user:{id} grant.
        $viewerEditorRole = ((int) $submission->user_id === $viewerId) ? 'requester' : null;
    @endphp

    {{-- Update draft form --}}
    @php
        $initialPayload = collect($form->fields)
            ->filter(fn($f) => !in_array($f->field_type, ['section', 'auto_number']))
            ->pluck('field_key')
            ->mapWithKeys(fn($k) => [$k => old("fields.{$k}", $submission->payload[$k] ?? '')])
            ->all();
    @endphp
    <form id="update-draft-form"
          method="POST"
          action="{{ route('forms.draft.update', $submission) }}"
          enctype="multipart/form-data" novalidate
          x-data="dynamicForm({{ json_encode($initialPayload, JSON_UNESCAPED_UNICODE) }})">
        @csrf @method('PUT')
        <div class="card p-6">
            <x-document-form-fields-grid :columns="$form->layout_columns ?? 1">
                @foreach($form->fields as $field)
                    @php
                        $fKey   = $field->field_key;
                        $fName  = "fields[{$fKey}]";
                        $fValue = old("fields.{$fKey}", $submission->payload[$fKey] ?? null);
                        $fSpan  = ($field->col_span && ($form->layout_columns ?? 1) > 1)
                            ? min($field->col_span, $form->layout_columns)
                            : 1;
                        $fVisRules = $field->visibility_rules ?? [];
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
                            'editorRole'   => $viewerEditorRole,
                            'editorUserId' => $viewerId ?: null,
                        ])
                      @if($xShowExpr)
                        </div>
                      @endif
                    </div>
                @endforeach
            </x-document-form-fields-grid>
        </div>
    </form>

    {{-- Action bar --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        {{-- Delete draft --}}
        <form method="POST"
              action="{{ route('forms.draft.destroy', $submission) }}"
              onsubmit="return confirm('{{ __('common.confirm_delete') }}')" novalidate>
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger">
                {{ __('common.delete_draft') }}
            </button>
        </form>

        <div class="flex gap-3">
            {{-- Save draft --}}
            <button type="submit" form="update-draft-form" class="btn-secondary">
                {{ __('common.save_draft') }}
            </button>

            {{-- Submit to workflow --}}
            <form method="POST"
                  action="{{ route('forms.draft.submit', $submission) }}"
                  onsubmit="return confirm('{{ __('common.confirm_submit_form') }}')" novalidate>
                @csrf
                @if(($overrideStages ?? collect())->isNotEmpty())
                    {{-- override: requester may optionally substitute the approver for these stages --}}
                    <div class="mb-3 space-y-2 text-left rounded-lg border border-blue-200 dark:border-blue-900/40 bg-blue-50/50 dark:bg-blue-900/10 p-3">
                        <p class="text-xs font-semibold text-blue-800 dark:text-blue-200">{{ __('common.submit_pick_approver_label') }}</p>
                        @foreach($overrideStages as $overrideStage)
                            <div>
                                <label class="text-xs text-slate-500 dark:text-slate-400">{{ $overrideStage->name }}</label>
                                <select name="picked_approvers[{{ $overrideStage->step_no }}]" class="form-input mt-1">
                                    <option value="">{{ __('common.submit_pick_approver_use_default') }}</option>
                                    @foreach(($eligibleApprovers ?? collect()) as $appr)
                                        <option value="{{ $appr['id'] }}">{{ $appr['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                @endif
                <button type="submit" class="btn-primary">
                    {{ __('common.submit_form') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
