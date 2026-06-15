@extends('layouts.app')

@section('title', __('common.holidays'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.holidays')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.holidays') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.holidays_hint') }}</p>
        </div>
        <form method="GET" action="{{ route('settings.holidays.index') }}">
            <select name="year" class="form-input text-sm" onchange="this.form.submit()">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if ($errors->any())
        <div class="alert-error mb-4"><p class="text-sm">{{ $errors->first() }}</p></div>
    @endif

    {{-- Add holiday --}}
    <div class="card p-4 mb-4">
        <form method="POST" action="{{ route('settings.holidays.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('common.date') }}</label>
                <input type="date" name="date" required value="{{ old('date') }}" class="form-input text-sm">
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('common.holiday_name') }}</label>
                <input type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                       placeholder="{{ __('common.holiday_name_placeholder') }}" class="form-input text-sm w-full">
            </div>
            <button type="submit" class="btn-primary">{{ __('common.add') }}</button>
        </form>
    </div>

    @if ($holidays->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_holidays_in_year', ['year' => $year]) }}</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="table-wrapper">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.date') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.holiday_name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    @foreach ($holidays as $month => $items)
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr class="bg-slate-50/60 dark:bg-slate-800/60">
                                <td colspan="4" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    {{ \Carbon\Carbon::createFromDate($year, (int) $month, 1)->locale(app()->getLocale())->translatedFormat('F Y') }}
                                </td>
                            </tr>
                            @foreach ($items as $holiday)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $holiday->is_active ? '' : 'opacity-60' }}">
                                    <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100 font-mono">
                                        {{ $holiday->date->locale(app()->getLocale())->translatedFormat('D d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">{{ $holiday->name }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($holiday->is_active)
                                            <span class="badge-success text-xs">{{ __('common.active') }}</span>
                                        @else
                                            <span class="badge-neutral text-xs">{{ __('common.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-row-actions :items="[
                                            ['label' => $holiday->is_active ? __('common.deactivate') : __('common.activate'), 'method' => 'PATCH', 'action' => route('settings.holidays.toggle', $holiday), 'icon' => 'toggle'],
                                            ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.holidays.destroy', $holiday), 'icon' => 'delete', 'confirm' => __('common.confirm_delete'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                                        ]" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
