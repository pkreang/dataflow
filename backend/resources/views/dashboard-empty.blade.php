@extends('layouts.app')

@section('title', __('common.dashboard'))

@section('content')
    <div class="card p-12 flex flex-col items-center text-center gap-4 text-slate-500 dark:text-slate-400">
        <svg class="w-16 h-16 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <div>
            <h2 class="text-lg font-semibold text-slate-700 dark:text-slate-200">
                {{ __('common.no_home_dashboard_title') }}
            </h2>
            <p class="text-sm mt-2 max-w-md">
                {{ __('common.no_home_dashboard_desc') }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reports.index') }}" class="btn-secondary">
                {{ __('common.go_to_reports') }}
            </a>
            @if($user?->is_super_admin)
                <a href="{{ route('settings.dashboards.create') }}" class="btn-primary">
                    {{ __('common.create_first_dashboard') }}
                </a>
            @endif
        </div>
    </div>
@endsection
