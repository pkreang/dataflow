@extends('layouts.app')

@section('title', __('common.kpi_cycles'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.kpi_cycles')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.kpi_cycles') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.kpi_cycles_subtitle') }}</p>
        </div>
        <a href="{{ route('settings.kpi-cycles.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.kpi_cycle_create') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'name', 'label' => __('common.kpi_cycle_name')],
            ['key' => 'form', 'label' => __('common.kpi_cycle_form')],
            ['key' => 'period', 'label' => __('common.kpi_cycle_period_start') . ' / ' . __('common.kpi_cycle_period_end')],
            ['key' => 'assignments', 'label' => __('common.kpi_cycle_assignments'), 'class' => 'text-right'],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$cycles"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.kpi-cycles.create')"
        :empty-cta-label="__('common.kpi_cycle_create')"
    >
        @foreach ($cycles as $cycle)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="table-primary">
                    <a href="{{ route('settings.kpi-cycles.edit', $cycle) }}" class="hover:text-blue-600">{{ $cycle->name }}</a>
                </td>
                <td class="table-sub">{{ $cycle->form?->name ?? '—' }}</td>
                <td class="table-sub">
                    {{ optional($cycle->period_start)->format('d/m/Y') ?? '—' }}
                    →
                    {{ optional($cycle->period_end)->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-4 py-3 text-right text-sm text-slate-500 dark:text-slate-400">
                    {{ $cycle->assignments->count() }}
                </td>
                <td class="px-4 py-3 text-sm">
                    @php
                        $cls = match ($cycle->status) {
                            'open' => 'badge-blue',
                            'closed' => 'badge-gray',
                            default => 'badge-yellow',
                        };
                    @endphp
                    <span class="{{ $cls }}">{{ __('common.kpi_cycle_status_' . $cycle->status) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    @php
                        $actions = [
                            ['label' => __('common.edit'), 'href' => route('settings.kpi-cycles.edit', $cycle), 'icon' => 'edit'],
                        ];
                        if ($cycle->status === 'draft') {
                            $actions[] = ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.kpi-cycles.destroy', $cycle), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $cycle->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'];
                        }
                    @endphp
                    <x-row-actions :items="$actions" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
