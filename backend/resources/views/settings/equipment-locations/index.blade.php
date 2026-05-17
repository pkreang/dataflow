@extends('layouts.app')

@section('title', __('common.equipment_locations'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.equipment_locations')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-2">
        <div>
            <nav class="text-sm text-slate-500 dark:text-slate-400 mb-1">
                <span>{{ __('common.settings') }}</span>
                <span class="mx-1">/</span>
                <span>{{ __('common.equipment_locations') }}</span>
            </nav>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.equipment_locations') }}</h2>
        </div>
        <a href="{{ route('settings.equipment-locations.create') }}" class="btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_equipment_location') }}
        </a>
    </div>

    <x-filter-bar :action="route('settings.equipment-locations.index')">
        <x-search-bar />
    </x-filter-bar>

    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif
    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'name', 'label' => __('common.name')],
            ['key' => 'code', 'label' => __('common.code')],
            ['key' => 'building', 'label' => __('common.building')],
            ['key' => 'floor', 'label' => __('common.floor')],
            ['key' => 'zone', 'label' => __('common.zone')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$locations"
        :empty-message="__('common.no_equipment_locations')"
        :empty-cta-href="route('settings.equipment-locations.create')"
        :empty-cta-label="__('common.add_equipment_location')"
        :disable-pagination="true"
    >
        @foreach ($locations as $location)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-3 text-xs font-mono text-slate-500 dark:text-slate-400">{{ $location->auto_code }}</td>
                <td class="table-primary">{{ $location->name }}</td>
                <td class="table-sub">{{ $location->code }}</td>
                <td class="table-sub">{{ $location->building ?? '—' }}</td>
                <td class="table-sub">{{ $location->floor ?? '—' }}</td>
                <td class="table-sub">{{ $location->zone ?? '—' }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    <x-status-badge :status="$location->is_active ? 'active' : 'inactive'" />
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.equipment-locations.edit', $location), 'icon' => 'edit'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.equipment-locations.destroy', $location), 'icon' => 'delete', 'confirm' => __('common.are_you_sure'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$locations" :perPage="$perPage" id="equipment-locations-pagination" />
</div>
@endsection
