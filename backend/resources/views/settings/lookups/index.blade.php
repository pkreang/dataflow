@extends('layouts.app')

@section('title', __('common.lookups'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.lookups')],
    ]" />
@endsection

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.lookups') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.lookups_desc') }}</p>
        </div>
        <a href="{{ route('settings.lookups.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_lookup_list') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if ($errors->has('delete'))
        <div class="alert-error mb-4">{{ $errors->first('delete') }}</div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'key', 'label' => 'Key'],
            ['key' => 'name', 'label' => __('common.name')],
            ['key' => 'items_count', 'label' => __('common.lookup_item_count'), 'class' => 'text-center'],
            ['key' => 'status', 'label' => __('common.status'), 'class' => 'text-center'],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$lists"
        :empty-message="__('common.lookups_empty')"
        :empty-cta-href="route('settings.lookups.create')"
        :empty-cta-label="__('common.add')"
        :disable-pagination="true"
    >
        @foreach ($lists as $list)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors duration-150">
                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100">{{ $list->auto_code }}</td>
                <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-300">
                    {{ $list->key }}
                    @if($list->is_system)
                        <span class="badge-gray ml-1 text-[10px]">{{ __('common.lookup_is_system') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ app()->getLocale() === 'th' ? $list->label_th : $list->label_en }}</div>
                    @if($list->description)
                        <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">{{ $list->description }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-center text-sm text-slate-600 dark:text-slate-300">{{ $list->items_count }}</td>
                <td class="px-4 py-3 text-center">
                    @if($list->is_active)
                        <span class="badge-green">{{ __('common.active') }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @php
                        $actions = [
                            ['label' => __('common.edit'), 'href' => route('settings.lookups.edit', $list), 'icon' => 'edit'],
                        ];
                        if (! $list->is_system) {
                            $actions[] = [
                                'label' => __('common.delete'),
                                'method' => 'DELETE',
                                'action' => route('settings.lookups.destroy', $list),
                                'icon' => 'delete',
                                'confirm' => __('common.confirm_delete'),
                                'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
                            ];
                        }
                    @endphp
                    <x-row-actions :items="$actions" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$lists" :perPage="$perPage" id="lookups-pagination" />
</div>
@endsection
