@extends('layouts.app')

@section('title', __('common.pm_am_plans'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.maintenance')],
    ]" />
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.pm_am_plans') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.pm_am_plans_desc') }}</p>
        </div>
        <a href="{{ route('maintenance.create-plan') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_pm_am_plan') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    <x-filter-bar :action="route('maintenance.index')">
        <div>
            <label class="form-label">{{ __('common.filter_by_status') }}</label>
            <select name="status" onchange="this.form.submit()" class="form-input">
                <option value="">{{ __('common.status_all') }}</option>
                <option value="pending" @selected(($status ?? '') === 'pending')>{{ __('common.approval_status_pending') }}</option>
                <option value="approved" @selected(($status ?? '') === 'approved')>{{ __('common.approval_status_approved') }}</option>
                <option value="rejected" @selected(($status ?? '') === 'rejected')>{{ __('common.approval_status_rejected') }}</option>
            </select>
        </div>
    </x-filter-bar>

    <div class="card p-5">
        <div class="space-y-2">
            @forelse($myInstances as $item)
                <a href="{{ route('maintenance.show', $item) }}" class="block rounded-lg border border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900/20 hover:border-blue-400 dark:hover:border-blue-500 transition-colors">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $item->reference_no ?: ('#' . $item->id) }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('common.approval_status_' . $item->status) }}
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

        <x-per-page-footer :paginator="$myInstances" :perPage="$perPage" id="maintenance-pagination" />
    </div>
@endsection
