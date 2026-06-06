@extends('layouts.app')

@section('title', __('common.workflow_policy_page_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_forms'), 'url' => route('settings.document-forms.index')],
        ['label' => __('common.workflow_policy_page_title')],
    ]" />
@endsection

@section('content')
    @php
        $docTypeLabel = match ($documentForm->document_type) {
            'repair_request' => __('common.doc_type_repair_request'),
            'pm_am_plan' => __('common.doc_type_pm_am_plan'),
            default => $documentForm->document_type,
        };
    @endphp
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.workflow_policy_for', ['name' => $documentForm->name]) }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $documentForm->form_key }} ({{ $docTypeLabel }})</p>
        </div>
        <a href="{{ route('settings.document-forms.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-data="policyBuilder({{ Js::from($policy->ranges->map(fn ($r) => ['min_amount' => $r->min_amount, 'max_amount' => $r->max_amount, 'workflow_id' => (string) $r->workflow_id])->values()) }})"
         class="card p-6">

        <form method="POST" action="{{ route('settings.document-forms.policy.update', $documentForm) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Department --}}
            <div class="max-w-md">
                <label class="form-label">{{ __('common.department_optional') }}</label>
                <select name="department_id" class="form-input">
                    <option value="">{{ __('common.global_default') }}</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(old('department_id', $policy->department_id) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('common.policy_department_hint') }}</p>
            </div>

            {{-- Mode Selection --}}
            <div>
                <label class="form-label mb-3">{{ __('common.workflow') }}</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="relative flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition"
                           :class="!useAmountCondition ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500'">
                        <input type="radio" name="_mode" value="fixed" class="mt-0.5" :checked="!useAmountCondition" @change="useAmountCondition = false">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ __('common.policy_mode_fixed') }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.policy_mode_fixed_hint') }}</p>
                        </div>
                    </label>
                    <label class="relative flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition"
                           :class="useAmountCondition ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500'">
                        <input type="radio" name="_mode" value="amount" class="mt-0.5" :checked="useAmountCondition" @change="useAmountCondition = true">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ __('common.policy_mode_amount') }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.policy_mode_amount_hint') }}</p>
                        </div>
                    </label>
                </div>
                <input type="hidden" name="use_amount_condition" :value="useAmountCondition ? '1' : '0'">
            </div>

            {{-- Fixed Workflow --}}
            <div x-show="!useAmountCondition" x-cloak class="max-w-md">
                <label class="form-label">{{ __('common.workflow') }}</label>
                <select name="workflow_id" class="form-input">
                    <option value="">{{ __('common.workflow_placeholder_select_workflow') }}</option>
                    @foreach($workflows as $workflow)
                        <option value="{{ $workflow->id }}" @selected(old('workflow_id', $policy->workflow_id) == $workflow->id)>{{ $workflow->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Amount Field Key --}}
            <div x-show="useAmountCondition" x-cloak class="max-w-md">
                <label class="form-label">{{ __('common.policy_amount_field_key') }}</label>
                <input type="text" name="amount_field_key" value="{{ old('amount_field_key', $policy->amount_field_key) }}"
                       class="form-input mt-1" placeholder="total_amount" maxlength="100">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('common.policy_amount_field_key_hint') }}</p>
            </div>

            {{-- Amount Ranges --}}
            <div x-show="useAmountCondition" x-cloak class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('common.amount_ranges') }}</h3>
                    <button type="button" @click="addRange()" class="btn-primary text-sm py-1.5 px-3">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('common.add_amount_range') }}
                    </button>
                </div>
                <template x-for="(range, idx) in ranges" :key="idx">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-700/50 p-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.amount_min') }}</label>
                            <input type="number" step="0.01" min="0" :name="`ranges[${idx}][min_amount]`" x-model="range.min_amount"
                                   class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.amount_max_hint') }}</label>
                            <input type="number" step="0.01" min="0" :name="`ranges[${idx}][max_amount]`" x-model="range.max_amount"
                                   class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('common.workflow') }}</label>
                            <select :name="`ranges[${idx}][workflow_id]`" x-model="range.workflow_id"
                                    class="form-input">
                                <option value="">{{ __('common.workflow_placeholder_select_workflow') }}</option>
                                @foreach($workflows as $workflow)
                                    <option value="{{ $workflow->id }}">{{ $workflow->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" @click="removeRange(idx)" class="w-full px-3 py-2 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 text-sm hover:bg-red-100 dark:hover:bg-red-900/40 transition">{{ __('common.delete') }}</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('settings.document-forms.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
            </div>
        </form>
    </div>

    <script>
        function policyBuilder(initialRanges) {
            return {
                useAmountCondition: {{ old('use_amount_condition', $policy->use_amount_condition) ? 'true' : 'false' }},
                ranges: initialRanges && initialRanges.length ? initialRanges : [{min_amount: 0, max_amount: '', workflow_id: ''}],
                addRange() {
                    this.ranges.push({min_amount: 0, max_amount: '', workflow_id: ''});
                },
                removeRange(idx) {
                    this.ranges.splice(idx, 1);
                }
            };
        }
    </script>
@endsection
