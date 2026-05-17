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
        <a href="{{ route('settings.running-numbers.create') }}" class="btn-primary">
            {{ __('common.add') }} {{ __('common.running_numbers') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'document_type', 'label' => __('common.document_type')],
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
            @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="table-primary">{{ $config->document_type }}</td>
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
