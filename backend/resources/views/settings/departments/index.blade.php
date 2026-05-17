@extends('layouts.app')

@section('title', __('common.departments'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.departments')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.department_list') }}</h2>
        <a href="{{ route('settings.departments.create') }}" class="btn-primary">
            {{ __('common.add') }} {{ __('common.departments') }}
        </a>
    </div>

    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'code', 'label' => __('common.code')],
            ['key' => 'name', 'label' => __('common.name')],
            ['key' => 'remark', 'label' => __('common.remark')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$departments"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.departments.create')"
        :empty-cta-label="__('common.add') . ' ' . __('common.departments')"
    >
        @foreach ($departments as $department)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100">{{ $department->auto_code }}</td>
                <td class="table-primary">{{ $department->code }}</td>
                <td class="table-primary">{{ $department->name }}</td>
                <td class="table-sub">{{ $department->description ?: '-' }}</td>
                <td class="px-4 py-3 text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.departments.edit', $department), 'icon' => 'edit'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.departments.destroy', $department), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $department->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
