@extends('layouts.app')

@section('title', __('common.branch_scoping_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.branch_scoping_title')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.branch_scoping_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.branch_scoping_page_subtitle') }}</p>
    </div>

    @if(session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 p-4 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/80 dark:bg-amber-950/30 text-sm text-amber-900 dark:text-amber-200">
        <p class="font-medium">{{ __('common.branch_scoping_hint_title') }}</p>
        <p class="mt-1 text-amber-800 dark:text-amber-300">{{ __('common.branch_scoping_hint_body') }}</p>
    </div>

    <form method="POST" action="{{ route('settings.branch-scoping.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <div class="card p-6">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ __('common.branches_management_section') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('common.branches_management_section_help') }}</p>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="toggle[branches.enabled]" value="0">
                    <input type="checkbox" name="toggle[branches.enabled]" value="1"
                           {{ ($settings['branches.enabled'] ?? '1') === '1' ? 'checked' : '' }}
                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ __('common.branches_management_toggle') }}</span>
                </label>
            </div>

            <div class="card p-6">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.branch_scoping_section_title') }}</p>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="toggle[branch_scoping.enabled]" value="0">
                    <input type="checkbox" name="toggle[branch_scoping.enabled]" value="1"
                           {{ ($settings['branch_scoping.enabled'] ?? '0') === '1' ? 'checked' : '' }}
                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('common.branch_scoping_master') }}</span>
                </label>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 ml-7">{{ __('common.branch_scoping_master_help') }}</p>

            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </div>
    </form>
@endsection
