@extends('layouts.app')

@section('title', 'Dashboards')

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.reports')],
    ]" />
@endsection

@section('content')
@php
    $totalDashboards = $dashboards->total();
@endphp
<div x-data="{ search: '' }">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Dashboards</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">ตัวออกแบบ Dashboard</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $totalDashboards }} dashboard(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.dashboards.create') }}" class="btn-primary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add_dashboard') }}
            </a>
        </div>
    </div>

    {{-- Search --}}
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

    @if ($dashboards->isEmpty())
        <x-table-empty-state card :message="__('common.no_data')" />
    @else
    <div class="table-wrapper">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="table-header">{{ __('common.name') }}</th>
                    <th class="table-header">Visibility</th>
                    <th class="table-header">Widgets</th>
                    <th class="table-header">{{ __('common.updated_at') }}</th>
                    <th class="table-header">{{ __('common.status') }}</th>
                    <th class="table-header text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($dashboards as $dashboard)
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
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if($dashboard->visibility === 'permission')
                                <span class="badge-yellow">
                                    Permission: {{ $dashboard->required_permission }}
                                </span>
                            @else
                                <span class="badge-gray">
                                    All Users
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $dashboard->widgets_count }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $dashboard->updated_at ? $dashboard->updated_at->format('M d, Y') : '-' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if ($dashboard->is_active)
                                <span class="badge-green">{{ __('common.active') }}</span>
                            @else
                                <span class="badge-red">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-right">
                            <x-row-actions :items="[
                                ['label' => __('common.edit'), 'href' => route('settings.dashboards.edit', $dashboard), 'icon' => 'edit'],
                                ['label' => $dashboard->is_active ? __('common.disable') : __('common.enable'), 'method' => 'PUT', 'action' => route('settings.dashboards.update', $dashboard), 'icon' => 'toggle', 'hidden' => ['toggle_active' => '1']],
                                ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.dashboards.destroy', $dashboard), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $dashboard->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                            ]" />
                        </td>
                    </tr>
                @empty
                    <x-table-empty-state :colspan="6" :message="__('common.no_data')"
                        :cta-href="route('settings.dashboards.create')" :cta-label="__('common.add')" />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-per-page-footer :paginator="$dashboards" :perPage="$perPage" id="dashboards-pagination" />
    @endif
</div>
@endsection
