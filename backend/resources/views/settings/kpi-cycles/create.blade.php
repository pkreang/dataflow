@extends('layouts.app')

@section('title', __('common.kpi_cycle_create'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.kpi_cycles'), 'url' => route('settings.kpi-cycles.index')],
        ['label' => __('common.kpi_cycle_create')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.kpi_cycle_create') }}</h2>
        <a href="{{ route('settings.kpi-cycles.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.kpi-cycles.store') }}" class="card p-5 space-y-4 max-w-2xl" novalidate>
        @csrf
        <div>
            <label class="form-label">{{ __('common.kpi_cycle_name') }} <span class="text-red-500">*</span></label>
            <input name="name" value="{{ old('name') }}" required class="form-input mt-1" />
        </div>
        <div>
            <label class="form-label">{{ __('common.kpi_cycle_form') }} <span class="text-red-500">*</span></label>
            <select name="form_id" required class="form-input mt-1">
                <option value="">—</option>
                @foreach ($forms as $form)
                    <option value="{{ $form->id }}" @selected(old('form_id') == $form->id)>{{ $form->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_period_start') }}</label>
                <input type="date" name="period_start" value="{{ old('period_start') }}" class="form-input mt-1" />
            </div>
            <div>
                <label class="form-label">{{ __('common.kpi_cycle_period_end') }}</label>
                <input type="date" name="period_end" value="{{ old('period_end') }}" class="form-input mt-1" />
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
@endsection
