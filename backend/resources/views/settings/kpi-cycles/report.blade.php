@extends('layouts.app')

@section('title', __('common.kpi_cycle_report') . ': ' . $cycle->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.kpi_cycles'), 'url' => route('settings.kpi-cycles.index')],
        ['label' => $cycle->name, 'url' => route('settings.kpi-cycles.edit', $cycle)],
        ['label' => __('common.kpi_cycle_report')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.kpi_cycle_report') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $cycle->name }}</p>
        </div>
        <a href="{{ route('settings.kpi-cycles.edit', $cycle) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if (empty($summary['targets']))
        <div class="card p-10 flex flex-col items-center gap-3 text-slate-400 dark:text-slate-500">
            <p class="text-sm font-medium">{{ __('common.kpi_cycle_report_no_data') }}</p>
        </div>
    @else
        <div class="table-wrapper">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="table-header">{{ __('common.kpi_cycle_report_target') }}</th>
                        <th class="table-header text-right">{{ __('common.kpi_cycle_role_self') }}</th>
                        <th class="table-header text-right">{{ __('common.kpi_cycle_role_supervisor') }}</th>
                        <th class="table-header text-right">{{ __('common.kpi_cycle_role_peer') }}</th>
                        <th class="table-header text-right font-bold">{{ __('common.kpi_cycle_report_overall_avg') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($summary['targets'] as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-5 py-3.5">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ trim(($row['user']?->first_name ?: '') . ' ' . ($row['user']?->last_name ?: '')) ?: ($row['user']?->email ?? '—') }}
                                </p>
                                @if($row['user']?->email)
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row['user']->email }}</p>
                                @endif
                            </td>
                            @foreach (['self', 'supervisor', 'peer'] as $role)
                                <td class="px-5 py-3 text-right">
                                    <x-kpi-role-cell :stat="$row[$role]" />
                                </td>
                            @endforeach
                            <td class="px-5 py-3 text-right">
                                @if ($row['overall_avg'] !== null)
                                    <span class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['overall_avg'], 2) }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
