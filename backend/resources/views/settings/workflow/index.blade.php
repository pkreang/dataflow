@extends('layouts.app')

@section('title', __('common.workflow'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.workflow')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.workflow_list_title') }}</h2>
        <a href="{{ route('settings.workflow.create') }}" class="btn-primary">
            {{ __('common.add') }} {{ __('common.workflow') }}
        </a>
    </div>

    <div class="alert-info mb-4">
        {{ __('common.workflow_routing_banner') }}
        <a href="{{ route('settings.approval-routing') }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">{{ __('common.approval_routing') }}</a>
    </div>

    @if (session('error'))
        <div class="alert-error mb-4">
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'name', 'label' => __('common.workflow_col_name')],
            ['key' => 'document_type', 'label' => __('common.document_type')],
            ['key' => 'stages', 'label' => __('common.workflow_col_stages')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$workflows"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.workflow.create')"
        :empty-cta-label="__('common.add') . ' ' . __('common.workflow')"
    >
        @foreach ($workflows as $workflow)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100">{{ $workflow->auto_code }}</td>
                <td class="table-primary">{{ $workflow->name }}</td>
                <td class="table-sub">{{ strtoupper($workflow->document_type) }}</td>
                <td class="table-sub">{{ $workflow->stages_count }}</td>
                <td class="px-4 py-3 text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.workflow.edit', $workflow), 'icon' => 'edit'],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.workflow.destroy', $workflow), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $workflow->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
