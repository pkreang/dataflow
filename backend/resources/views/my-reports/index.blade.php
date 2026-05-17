@extends('layouts.app')

@section('title', __('common.my_reports'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.my_reports')],
    ]" />
@endsection

@section('content')
@php
    $totalDashboards = $dashboards->total();
@endphp
<div x-data="{ search: '' }">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.my_reports') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('common.my_reports_subtitle') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $totalDashboards }} {{ __('common.dashboards') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('my-reports.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add') }}
            </a>
        </div>
    </div>

    <div class="mb-5">
        <div class="relative max-w-sm">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" x-model="search" placeholder="{{ __('common.search') }}..."
                   class="form-input" style="padding-left: 2.5rem;">
        </div>
    </div>

    @if (session('error'))
        <div class="alert-error mb-4">
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if($dashboards->total() === 0)
        <div class="card p-12 text-center">
            <svg class="w-10 h-10 mx-auto text-slate-400 dark:text-slate-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">{{ __('common.my_reports_empty_title') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('common.my_reports_empty_hint') }}</p>
            <a href="{{ route('my-reports.create') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add') }}
            </a>
        </div>
    @else
    <div class="table-wrapper">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="table-header">{{ __('common.name') }}</th>
                    <th class="table-header">{{ __('common.widgets') }}</th>
                    <th class="table-header">{{ __('common.status') }}</th>
                    <th class="table-header">{{ __('common.updated_at') }}</th>
                    <th class="table-header text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($dashboards as $dashboard)
                    @php
                        $searchBlob = Str::lower($dashboard->name . ' ' . ($dashboard->description ?? ''));
                    @endphp
                    <tr
                        class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150"
                        data-search="{{ e($searchBlob) }}"
                        x-show="!search.trim() || ($el.dataset.search || '').includes(search.toLowerCase())"
                    >
                        <td class="px-6 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $dashboard->name }}</p>
                                    @if($dashboard->description)
                                        <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ Str::limit($dashboard->description, 60) }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $dashboard->widgets_count }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if ($dashboard->is_active)
                                <span class="badge-green">{{ __('common.active') }}</span>
                            @else
                                <span class="badge-red">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $dashboard->updated_at ? $dashboard->updated_at->format('M d, Y') : '-' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-right">
                            <x-row-actions :items="[
                                ['label' => __('common.view'), 'href' => route('reports.dashboards.show', $dashboard), 'icon' => 'view'],
                                ['label' => __('common.edit'), 'href' => route('my-reports.edit', $dashboard), 'icon' => 'edit'],
                                [
                                    'label' => __('common.delete'),
                                    'method' => 'DELETE',
                                    'action' => route('my-reports.destroy', $dashboard),
                                    'icon' => 'delete',
                                    'class' => 'text-red-600 dark:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-700',
                                    'confirm' => __('common.delete_confirm_msg', ['name' => $dashboard->name]),
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-per-page-footer :paginator="$dashboards" :perPage="$perPage" id="my-reports-pagination" />
    @endif
</div>
@endsection
