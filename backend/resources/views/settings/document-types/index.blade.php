@extends('layouts.app')

@section('title', __('common.document_types'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_types')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.document_types') }}</h2>
        <a href="{{ route('settings.document-types.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_document_type') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'code', 'label' => __('common.code')],
            ['key' => 'label_en', 'label' => __('common.label') . ' (EN)'],
            ['key' => 'label_th', 'label' => __('common.label') . ' (TH)'],
            ['key' => 'icon', 'label' => __('common.icon')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$documentTypes"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.document-types.create')"
        :empty-cta-label="__('common.add') . ' ' . __('common.document_types')"
        :disable-pagination="true"
    >
        @foreach ($documentTypes as $type)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100">{{ $type->code }}</td>
                <td class="table-sub">{{ $type->label_en }}</td>
                <td class="table-sub">{{ $type->label_th }}</td>
                <td class="table-sub">
                    @if ($type->icon)
                        <span class="inline-flex items-center gap-2">
                            <x-nav-icon :name="$type->icon" class="w-5 h-5 text-slate-700 dark:text-slate-200" />
                            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $type->icon }}</span>
                        </span>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm">
                    @if ($type->is_active)
                        <span class="badge-green">{{ __('common.active') }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.document-types.edit', $type), 'icon' => 'edit'],
                        ['label' => $type->is_active ? __('common.disable') : __('common.enable'), 'method' => 'PUT', 'action' => route('settings.document-types.update', $type), 'icon' => 'toggle', 'hidden' => ['toggle_active' => '1']],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.document-types.destroy', $type), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $type->label_en]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$documentTypes" :perPage="$perPage" id="document-types-pagination" />
@endsection
