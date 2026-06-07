@extends('layouts.app')

@section('title', __('common.repair_request'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.repair_request')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.repair_request') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.repair_request_desc') }}</p>
    </div>

    @if ($errors->has('workflow'))
        <div class="alert-error mb-4">
            {{ $errors->first('workflow') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (!empty($showAdminHints))
        <div class="alert-warning mb-4">
            <p class="font-medium mb-2">{{ __('common.repair_admin_setup_intro') }}</p>
            <ul class="list-disc list-inside space-y-1">
                <li><a href="{{ route('settings.workflow.index') }}" class="underline hover:no-underline">{{ __('common.repair_admin_link_workflow') }}</a></li>
                <li><a href="{{ route('settings.document-forms.index') }}" class="underline hover:no-underline">{{ __('common.repair_admin_link_forms') }}</a></li>
                <li><a href="{{ route('settings.approval-routing') }}" class="underline hover:no-underline">{{ __('common.repair_admin_link_routing') }}</a></li>
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-5">
            @include('repair-requests._company_header', ['company' => $company ?? null, 'branch' => $branch ?? null])
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.submit') }}</h3>
            <form method="POST" action="{{ route('repair-requests.submit') }}" class="space-y-3" novalidate
                  x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                @if($form)
                    <input type="hidden" name="form_key" value="{{ $form->form_key }}">
                @endif
                <div>
                    <label for="reference_no" class="form-label">{{ __('common.reference_no') }}</label>
                    <input id="reference_no" name="reference_no" value="{{ old('reference_no') }}"
                           class="form-input mt-1">
                </div>
                <div>
                    <label for="department_id" class="form-label">{{ __('common.department') }}</label>
                    <select id="department_id" name="department_id"
                            class="form-input mt-1">
                        <option value="">{{ __('common.department_not_selected') }}</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($form)
                    @php
                        $layoutCols = (int) ($form->layout_columns ?? 1);
                        $layoutClass = match($layoutCols) {
                            2 => 'grid grid-cols-1 md:grid-cols-2 gap-4',
                            3 => 'grid grid-cols-1 md:grid-cols-3 gap-4',
                            4 => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4',
                            default => 'grid grid-cols-1 gap-4',
                        };
                    @endphp
                    <div class="{{ $layoutClass }}">
                    @foreach($form->fields as $field)
                        @php
                            $name = "form_payload[{$field->field_key}]";
                            $value = old("form_payload.{$field->field_key}");
                            $isSection = $field->field_type === 'section';
                            $span = $isSection ? $layoutCols : (($field->col_span && $layoutCols > 1) ? min($field->col_span, $layoutCols) : 1);
                        @endphp
                        <div @if($span > 1) style="grid-column: span {{ $span }}" @endif>
                            @if(!$isSection)
                                <label for="field_{{ $field->field_key }}" class="form-label">{{ $field->label }}</label>
                            @endif
                            @include('components.dynamic-field', ['field' => $field, 'name' => $name, 'value' => $value, 'userDeptId' => $userDeptId ?? null])
                            @if(!$isSection)
                                @error('form_payload.' . $field->field_key)
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    @endforeach
                    </div>
                    <div>
                        <label for="amount" class="form-label">{{ __('common.amount_for_workflow') }}</label>
                        <input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}"
                               class="form-input mt-1">
                    </div>
                @endif
                @error('form_payload.title')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <button type="submit"
                        :disabled="submitting"
                        class="btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="submitting ? '{{ __('common.submitting') }}' : '{{ __('common.submit') }}'"></span>
                </button>
            </form>
        </div>

        <div class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.my_submitted_requests') }}</h3>
                @if(in_array('approval.approve', session('user_permissions', []), true))
                    <a href="{{ route('approvals.my') }}" class="text-sm text-blue-600 hover:text-blue-700 whitespace-nowrap">{{ __('common.my_approvals') }}</a>
                @endif
            </div>

            <form method="GET" action="{{ route('repair-requests.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <label class="text-xs text-slate-500 dark:text-slate-400 block mb-1">{{ __('common.filter_by_status') }}</label>
                    <select name="status" onchange="this.form.submit()" class="form-input text-sm">
                        <option value="">{{ __('common.status_all') }}</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>{{ __('common.approval_status_pending') }}</option>
                        <option value="approved" @selected(($status ?? '') === 'approved')>{{ __('common.approval_status_approved') }}</option>
                        <option value="rejected" @selected(($status ?? '') === 'rejected')>{{ __('common.approval_status_rejected') }}</option>
                    </select>
                </div>
            </form>

            <div class="space-y-2">
                @forelse($myInstances as $item)
                    <a href="{{ route('repair-requests.show', $item) }}" class="block rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900/20 hover:border-blue-400 dark:hover:border-blue-500 transition-colors">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $item->reference_no ?: ('#' . $item->id) }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <x-status-badge :status="$item->status" />
                            · {{ __('common.workflow_step_short') }} {{ $item->current_step_no }}
                            @if($item->department)
                                · {{ $item->department->name }}
                            @endif
                        </p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_data') }}</p>
                @endforelse
            </div>

            <x-per-page-footer :paginator="$myInstances" :perPage="$perPage" id="repair-requests-pagination" />
        </div>
    </div>
@endsection
