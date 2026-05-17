@extends('layouts.app')

@section('title', __('common.running_numbers'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.running_numbers')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.running_numbers') }}</h2>
        <a href="{{ route('settings.running-numbers.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_running_number') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'document_type', 'label' => __('common.document_type')],
            ['key' => 'used_by', 'label' => __('common.running_number_used_by_forms')],
            ['key' => 'prefix', 'label' => __('common.running_number_prefix')],
            ['key' => 'preview', 'label' => __('common.running_number_preview')],
            ['key' => 'current', 'label' => __('common.running_number_current')],
            ['key' => 'reset_mode', 'label' => __('common.running_number_reset_mode')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$configs"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.running-numbers.create')"
        :empty-cta-label="__('common.add') . ' ' . __('common.running_numbers')"
        :disable-pagination="true"
    >
        @foreach ($configs as $config)
            @php
                $now = now();
                $preview = $config->prefix;
                if ($config->include_year) $preview .= $now->format('Y');
                if ($config->include_month) $preview .= $now->format('m');
                $preview .= '-' . str_pad($config->last_number + 1, $config->digit_count, '0', STR_PAD_LEFT);
                $usingForms = $formsByType[$config->document_type] ?? [];
            @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-3 text-xs font-mono text-slate-500 dark:text-slate-400">{{ $config->auto_code }}</td>
                <td class="table-primary">
                    <span class="inline-flex items-center gap-1.5">
                        @if ($iconName = \App\Models\DocumentType::iconFor($config->document_type))
                            <x-nav-icon :name="$iconName" class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" />
                        @endif
                        <span>{{ $config->document_type }}</span>
                    </span>
                </td>
                <td class="px-4 py-3 text-sm">
                    @if (count($usingForms) === 0)
                        <span class="text-orange-500 dark:text-orange-400 italic">{{ __('common.running_number_no_forms_using') }}</span>
                    @else
                        <span class="text-slate-700 dark:text-slate-300" title="{{ implode(', ', $usingForms) }}">
                            <span class="font-medium">{{ count($usingForms) }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">· {{ \Illuminate\Support\Str::limit(implode(', ', $usingForms), 60) }}</span>
                        </span>
                    @endif
                </td>
                <td class="table-sub">{{ $config->prefix }}</td>
                <td class="px-4 py-3 text-sm font-mono text-blue-600 dark:text-blue-400">{{ $preview }}</td>
                <td class="table-sub">{{ $config->last_number }}</td>
                <td class="table-sub">{{ __('common.running_number_reset_' . $config->reset_mode) }}</td>
                <td class="px-4 py-3 text-sm">
                    @if ($config->is_active)
                        <span class="badge-green">{{ __('common.active') }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.running-numbers.edit', $config), 'icon' => 'edit'],
                        ['label' => __('common.running_number_reset_counter'), 'method' => 'POST', 'action' => route('settings.running-numbers.reset', $config), 'confirm' => __('common.running_number_reset_confirm'), 'class' => 'text-orange-600 dark:text-orange-400 hover:bg-slate-100 dark:hover:bg-slate-700'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.running-numbers.destroy', $config), 'icon' => 'delete', 'confirm' => __('common.delete_confirm'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$configs" :perPage="$perPage" id="running-numbers-pagination" />
@endsection
