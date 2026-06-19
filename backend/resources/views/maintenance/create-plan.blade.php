@extends('layouts.app')

@section('title', __('common.create_pm_am_plan'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.maintenance'), 'url' => route('maintenance.index')],
        ['label' => __('common.create_pm_am_plan')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <a href="{{ route('maintenance.index') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ __('common.create_pm_am_plan') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.create_pm_am_plan_desc') }}</p>
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

    <div class="max-w-2xl">
        <div class="card p-5">
            @include('repair-requests._company_header', ['company' => $company ?? null, 'branch' => $branch ?? null])
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.submit') }}</h3>
            <form method="POST" action="{{ route('maintenance.create-plan.submit') }}" class="space-y-3" novalidate>
                @csrf
                @if($form)
                    <input type="hidden" name="form_key" value="{{ $form->form_key }}">
                @endif
                <div>
                    <label class="form-label">{{ __('common.reference_no') }}</label>
                    <input name="reference_no" value="{{ old('reference_no') }}" class="form-input mt-1">
                </div>
                <div>
                    <label class="form-label">{{ __('common.department') }}</label>
                    <select name="department_id" class="form-input mt-1">
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
                                <label class="form-label">{{ $field->label }}</label>
                            @endif
                            @if($field->field_key === 'equipment_id')
                                <select name="{{ $name }}" @required($field->is_required) class="form-input mt-1">
                                    <option value="">{{ __('common.please_select') }}</option>
                                    @foreach($equipmentList as $eq)
                                        <option value="{{ $eq->id }}" @selected($value == $eq->id)>[{{ $eq->code }}] {{ $eq->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                @include('components.dynamic-field', ['field' => $field, 'name' => $name, 'value' => $value, 'userDeptId' => $userDeptId ?? null, 'userOrgUnitId' => $userOrgUnitId ?? null])
                            @endif
                            @if(!$isSection)
                                @error('form_payload.' . $field->field_key)
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>
                    @endforeach
                    </div>
                    <div>
                        <label class="form-label">{{ __('common.amount_for_workflow') }}</label>
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="form-input mt-1">
                    </div>
                @endif
                @error('form_payload.title')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <button class="btn-primary">{{ __('common.submit') }}</button>
            </form>
        </div>
    </div>
@endsection
