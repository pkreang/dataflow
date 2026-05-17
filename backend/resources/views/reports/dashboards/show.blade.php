@extends('layouts.app')

@section('title', $dashboard->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.reports'), 'url' => route('reports.index')],
        ['label' => $dashboard->name],
    ]" />
@endsection

@push('scripts')
<meta name="api-token" content="{{ $apiToken ?? '' }}">
@endpush

@section('content')
<div class="mb-6">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $dashboard->name }}</h2>
    @if($dashboard->description)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $dashboard->description }}</p>
    @endif
</div>

{{-- Global filter bar --}}
<div x-data="{}"
     class="mb-6 flex flex-wrap items-end gap-3 card p-4">
    <div class="flex flex-col gap-1">
        <label for="filter-date-from" class="text-xs font-medium text-slate-500 dark:text-slate-400">Date From</label>
        <input type="date" id="filter-date-from"
               class="form-input py-1.5">
    </div>
    <div class="flex flex-col gap-1">
        <label for="filter-date-to" class="text-xs font-medium text-slate-500 dark:text-slate-400">Date To</label>
        <input type="date" id="filter-date-to"
               class="form-input py-1.5">
    </div>
    <div class="flex flex-col gap-1">
        <label for="filter-department" class="text-xs font-medium text-slate-500 dark:text-slate-400">Department</label>
        <select id="filter-department"
                class="form-input py-1.5">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="button"
            onclick="document.querySelectorAll('[data-dashboard-widget]').forEach(el => { Alpine.$data(el)?.loadData?.(); })"
            class="btn-primary py-1.5">
        {{ __('common.refresh') ?? 'Refresh' }}
    </button>
    @php
        $hasExportable = $dashboard->widgets->whereIn('widget_type', ['table', 'chart'])->isNotEmpty();
    @endphp
    @if($hasExportable)
        <button type="button"
                onclick="window.downloadDashboardZip({{ $dashboard->id }})"
                class="btn-secondary py-1.5">
            {{ __('common.action_download_all_csv') }}
        </button>
    @endif
</div>

{{-- Widget grid --}}
<div class="grid gap-4" style="grid-template-columns: repeat({{ $dashboard->layout_columns ?? 2 }}, minmax(0, 1fr))">
    @foreach($dashboard->widgets as $widget)
        <div class="card p-4"
             style="grid-column: span {{ $widget->col_span ?: 1 }}"
             data-dashboard-widget
             x-data="dashboardWidget({{ $widget->id }}, {{ $dashboard->id }}, '{{ $widget->widget_type }}')"
             x-init="loadData()">

            {{-- Widget header --}}
            <div class="flex items-center justify-between mb-3 gap-2">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $widget->title }}</h3>
                @if(in_array($widget->widget_type, ['table', 'chart'], true))
                    <button type="button"
                            @click="downloadCsv()"
                            title="{{ __('common.action_download_csv') }}"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        CSV
                    </button>
                @endif
            </div>

            {{-- Loading state (skeleton — avoids blank jump) --}}
            <div x-show="loading" x-cloak>
                <template x-if="widgetType === 'metric'">
                    <div><x-skeleton-widget variant="metric" /></div>
                </template>
                <template x-if="widgetType === 'chart'">
                    <div><x-skeleton-widget variant="chart" /></div>
                </template>
                <template x-if="widgetType === 'table'">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <tbody>
                                <x-skeleton-rows :rows="4" :cols="4" />
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            {{-- Error state --}}
            <div x-show="error && !loading" class="text-sm text-red-500 p-2" x-text="error"></div>

            {{-- Metric widget --}}
            <div x-show="!loading && !error && widgetType === 'metric'">
                <p class="text-3xl font-bold text-slate-900 dark:text-slate-100" x-text="data.value ?? '-'"></p>
                <template x-if="data.label">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-text="data.label"></p>
                </template>
            </div>

            {{-- Chart widget --}}
            <div x-show="!loading && !error && widgetType === 'chart'">
                <div x-show="(data.labels || []).length > 0" style="position: relative; height: 200px;">
                    <canvas :id="`chart-${widgetId}`"
                            data-chart-type="{{ $widget->config['chart_type'] ?? 'bar' }}"
                            height="200"></canvas>
                </div>
                <div x-show="(data.labels || []).length === 0" x-cloak
                     class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 py-12 gap-2">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <p class="text-sm">{{ __('common.no_data') }}</p>
                </div>
            </div>

            {{-- Table widget --}}
            <div x-show="!loading && !error && widgetType === 'table'" class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <thead>
                        <tr>
                            <template x-for="label in (data.column_labels || [])">
                                <th class="table-header" x-text="label"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <template x-for="(row, ridx) in (data.rows || [])" :key="ridx">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <template x-for="col in (data.columns || [])" :key="col">
                                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300 whitespace-nowrap" x-text="row[col] ?? '-'"></td>
                                </template>
                            </tr>
                        </template>
                        <template x-if="!data.rows || data.rows.length === 0">
                            <tr>
                                <td :colspan="(data.columns || []).length || 1" class="px-3 py-4 text-center text-slate-400">No data</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                {{-- Pagination --}}
                <div x-show="data.pagination && data.pagination.last_page > 1"
                     class="flex items-center justify-between mt-3 text-sm text-slate-500 dark:text-slate-400">
                    <span x-text="`Page ${data.pagination?.current_page} of ${data.pagination?.last_page}`"></span>
                    <div class="flex gap-2">
                        <button type="button"
                                @click="prevPage()"
                                :disabled="data.pagination?.current_page <= 1"
                                class="inline-flex items-center justify-center min-h-11 min-w-11 px-3 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 disabled:opacity-40 transition-colors text-sm font-medium">
                            Prev
                        </button>
                        <button type="button"
                                @click="nextPage()"
                                :disabled="data.pagination?.current_page >= data.pagination?.last_page"
                                class="inline-flex items-center justify-center min-h-11 min-w-11 px-3 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 disabled:opacity-40 transition-colors text-sm font-medium">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($dashboard->widgets->isEmpty())
    <div class="card p-12 text-center">
        <p class="text-slate-500 dark:text-slate-400">No widgets have been added to this dashboard yet.</p>
    </div>
@endif

@endsection
