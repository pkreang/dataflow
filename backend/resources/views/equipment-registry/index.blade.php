@extends('layouts.app')

@section('title', __('common.equipment_list'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'CMMS'],
        ['label' => __('common.equipment_registry_title')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.equipment_list') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.total') }}: {{ $equipment->total() }}</p>
        </div>
        <a href="{{ route('equipment-registry.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_equipment') }}
        </a>
    </div>

    {{-- Search & Filters --}}
    <x-filter-bar :action="route('equipment-registry.index')">
        <x-search-bar :placeholder="__('common.search_equipment')" />
        <select name="category_id" onchange="this.form.submit()" class="form-input w-auto">
            <option value="">{{ __('common.all_categories') }}</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="location_id" onchange="this.form.submit()" class="form-input w-auto">
            <option value="">{{ __('common.all_locations') }}</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()" class="form-input w-auto">
            <option value="">{{ __('common.all_statuses') }}</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('common.status_active') }}</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.status_inactive') }}</option>
            <option value="under_maintenance" {{ request('status') == 'under_maintenance' ? 'selected' : '' }}>{{ __('common.status_under_maintenance') }}</option>
            <option value="decommissioned" {{ request('status') == 'decommissioned' ? 'selected' : '' }}>{{ __('common.status_decommissioned') }}</option>
        </select>
    </x-filter-bar>

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

    <x-data-table
        :columns="[
            ['key' => 'name_code', 'label' => __('common.name') . ' / ' . __('common.code')],
            ['key' => 'serial', 'label' => __('common.serial_number')],
            ['key' => 'category', 'label' => __('common.category')],
            ['key' => 'location', 'label' => __('common.location')],
            ['key' => 'criticality', 'label' => __('common.criticality')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$equipment"
        :disable-pagination="true"
        :empty-message="__('common.no_equipment_found')"
        :empty-cta-href="route('equipment-registry.create')"
        :empty-cta-label="__('common.add_equipment')"
    >
        @foreach ($equipment as $item)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-2 whitespace-nowrap">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $item->name }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $item->code }}</p>
                </td>
                <td class="table-sub">{{ $item->serial_number ?? '—' }}</td>
                <td class="table-sub">{{ $item->category->name ?? '—' }}</td>
                <td class="table-sub">{{ $item->location->name ?? '—' }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    @switch($item->criticality)
                        @case('A')
                            <span class="badge-red">A</span>
                            @break
                        @case('B')
                            <span class="badge-yellow">B</span>
                            @break
                        @case('C')
                            <span class="badge-gray">C</span>
                            @break
                        @default
                            <span class="text-slate-400 text-xs">—</span>
                    @endswitch
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    @switch($item->status)
                        @case('active')
                            <span class="badge-green">{{ __('common.status_active') }}</span>
                            @break
                        @case('inactive')
                            <span class="badge-red">{{ __('common.status_inactive') }}</span>
                            @break
                        @case('under_maintenance')
                            <span class="badge-yellow">{{ __('common.status_under_maintenance') }}</span>
                            @break
                        @case('decommissioned')
                            <span class="badge-gray">{{ __('common.status_decommissioned') }}</span>
                            @break
                    @endswitch
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('equipment-registry.edit', $item), 'icon' => 'edit'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('equipment-registry.destroy', $item), 'icon' => 'delete', 'confirm' => __('common.are_you_sure'), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$equipment" :perPage="$perPage" id="equipment-registry-pagination" />
</div>
@endsection
