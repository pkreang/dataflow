@extends('layouts.app')

@section('title', __('common.pm_plans'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.cmms'), 'url' => null],
        ['label' => __('common.pm_plans')],
    ]" />
@endsection

@section('content')
<div class="w-full">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.pm_plans') }}</h2>
        @can('pm.plan')
            <a href="{{ route('cmms.pm.plans.create') }}" class="btn-primary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add_pm_plan') }}
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif

    <x-filter-bar :action="route('cmms.pm.plans.index')">
        <input name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}" class="form-input w-auto" />
        <select name="equipment_id" onchange="this.form.submit()" class="form-input w-auto">
            <option value="">{{ __('common.all_equipment') }}</option>
            @foreach($equipmentList as $eq)
                <option value="{{ $eq->id }}" {{ request('equipment_id') == $eq->id ? 'selected' : '' }}>{{ $eq->code }} — {{ $eq->name }}</option>
            @endforeach
        </select>
        <select name="frequency_type" onchange="this.form.submit()" class="form-input w-auto">
            <option value="">{{ __('common.all_frequency_types') }}</option>
            <option value="date" {{ request('frequency_type') === 'date' ? 'selected' : '' }}>{{ __('common.pm_frequency_date') }}</option>
            <option value="runtime" {{ request('frequency_type') === 'runtime' ? 'selected' : '' }}>{{ __('common.pm_frequency_runtime') }}</option>
        </select>
    </x-filter-bar>

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'equipment', 'label' => __('common.equipment')],
            ['key' => 'name', 'label' => __('common.pm_plan_name')],
            ['key' => 'frequency', 'label' => __('common.pm_frequency')],
            ['key' => 'tasks', 'label' => __('common.pm_task_count')],
            ['key' => 'next_due', 'label' => __('common.pm_next_due_at')],
            ['key' => 'is_active', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$plans"
        :disable-pagination="true"
        :empty-message="__('common.no_pm_plans_found')"
        :empty-cta-href="route('cmms.pm.plans.create')"
        :empty-cta-label="__('common.add_pm_plan')"
    >
        @foreach($plans as $plan)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-2 text-sm font-mono text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ $plan->auto_code }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $plan->equipment?->code ?? '—' }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $plan->equipment?->name }}</p>
                </td>
                <td class="px-4 py-2">
                    <p class="text-sm text-slate-900 dark:text-slate-100">{{ $plan->name }}</p>
                    @if($plan->description)
                        <p class="text-xs text-slate-400 dark:text-slate-500 line-clamp-1">{{ $plan->description }}</p>
                    @endif
                </td>
                <td class="table-sub">
                    @if($plan->frequency_type === 'date')
                        <span class="badge-blue">{{ __('common.pm_frequency_date') }}</span>
                        <span class="text-xs ml-1">{{ $plan->interval_days }} {{ __('common.days') }}</span>
                    @else
                        <span class="badge-yellow">{{ __('common.pm_frequency_runtime') }}</span>
                        <span class="text-xs ml-1">{{ number_format((float) $plan->interval_hours, 0) }} {{ __('common.hours') }}</span>
                    @endif
                </td>
                <td class="table-sub text-center">{{ $plan->taskItems->count() }}</td>
                <td class="table-sub">
                    @if($plan->next_due_at)
                        {{ $plan->next_due_at->format('Y-m-d') }}
                    @elseif($plan->next_due_runtime)
                        {{ number_format((float) $plan->next_due_runtime, 0) }} {{ __('common.hours') }}
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    @if($plan->is_active)
                        <span class="badge-green">{{ __('common.status_active') }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.status_inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('cmms.pm.plans.edit', $plan), 'icon' => 'edit'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('cmms.pm.plans.destroy', $plan), 'icon' => 'delete', 'confirm' => __('common.are_you_sure'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$plans" :perPage="$perPage" id="pm-plans-pagination" />
</div>
@endsection
