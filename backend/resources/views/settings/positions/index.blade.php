@extends('layouts.app')

@section('title', __('common.positions'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.positions')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.position_list') }}</h2>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.positions.import') }}" class="btn-secondary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                {{ __('common.import') }}
            </a>
            <a href="{{ route('settings.positions.create') }}" class="btn-primary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add_position') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'code', 'label' => __('common.code')],
            ['key' => 'name', 'label' => __('common.name')],
            ['key' => 'remark', 'label' => __('common.remark')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$positions"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.positions.create')"
        :empty-cta-label="__('common.add') . ' ' . __('common.positions')"
        :disable-pagination="true"
    >
        @foreach ($positions as $position)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="table-primary">{{ $position->code }}</td>
                <td class="table-primary">{{ $position->name }}</td>
                <td class="table-sub">{{ $position->description ?: '-' }}</td>
                <td class="px-4 py-3 text-sm">
                    <x-status-badge :status="$position->is_active ? 'active' : 'inactive'" />
                </td>
                <td class="px-4 py-3 text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.positions.edit', $position), 'icon' => 'edit'],
                        ['label' => $position->is_active ? __('common.disable') : __('common.enable'), 'method' => 'PUT', 'action' => route('settings.positions.update', $position), 'icon' => 'toggle', 'hidden' => ['toggle_active' => '1']],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.positions.destroy', $position), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $position->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$positions" :perPage="$perPage" id="positions-pagination" />
@endsection
