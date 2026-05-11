@extends('layouts.app')

@section('title', __('common.document_forms'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_forms')],
    ]" />
@endsection

@section('content')
@php
    $totalForms = $forms->count();
@endphp
<div x-data="{ search: '' }" class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.document_forms') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('common.document_forms_desc') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.document_forms_list_subtitle', ['count' => $totalForms]) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.document-forms.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add') }}
            </a>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-5">
        <div class="relative max-w-sm">
            <svg class="w-4 h-4 text-slate-400 dark:text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" x-model="search" placeholder="{{ __('common.search_forms_placeholder') }}"
                   class="form-input w-full" style="padding-left: 2.5rem;">
        </div>
    </div>

    @if (session('error'))
        <div class="alert-error mb-4">{{ session('error') }}</div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'auto_code', 'label' => __('common.system_code')],
            ['key' => 'name', 'label' => __('common.name')],
            ['key' => 'document_type', 'label' => __('common.document_type')],
            ['key' => 'fields', 'label' => __('common.fields')],
            ['key' => 'workflow_policy', 'label' => __('common.workflow_policy')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'updated_at', 'label' => __('common.updated_at')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$forms"
        :empty-message="__('common.no_data')"
        :empty-cta-href="route('settings.document-forms.create')"
        :empty-cta-label="__('common.add')"
    >
        @foreach ($forms as $form)
            @php
                $searchBlob = Str::lower($form->name . ' ' . $form->form_key . ' ' . $form->document_type);
            @endphp
            <tr
                class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150"
                data-search="{{ e($searchBlob) }}"
                x-show="!search.trim() || ($el.dataset.search || '').includes(search.toLowerCase())"
            >
                <td class="px-4 py-3 text-sm font-mono text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ $form->auto_code }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-violet-500 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $form->name }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 truncate font-mono">{{ $form->form_key }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="badge-gray">{{ $form->document_type }}</span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                    {{ $form->fields_count }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @php $mainPolicy = $form->workflowPolicies->first(); @endphp
                    @if (!$mainPolicy)
                        <span class="badge-gray">{{ __('common.policy_summary_not_configured') }}</span>
                    @elseif ($mainPolicy->use_amount_condition)
                        <span class="badge-blue">{{ __('common.policy_summary_amount_ranges', ['count' => $mainPolicy->ranges->count()]) }}</span>
                    @elseif ($mainPolicy->workflow)
                        <span class="badge-blue">{{ $mainPolicy->workflow->name }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.policy_summary_not_configured') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if ($form->is_active)
                        <span class="badge-green">{{ __('common.active') }}</span>
                    @else
                        <span class="badge-red">{{ __('common.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                    {{ $form->updated_at ? $form->updated_at->format('M d, Y') : '-' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-right">
                    <x-row-actions :items="[
                        ['label' => __('common.edit'), 'href' => route('settings.document-forms.edit', $form), 'icon' => 'edit'],
                        ['label' => __('common.workflow_policy'), 'href' => route('settings.document-forms.policy.edit', $form)],
                        ['label' => __('common.clone'), 'method' => 'POST', 'action' => route('settings.document-forms.clone', $form)],
                        ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.document-forms.destroy', $form), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $form->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                    ]" />
                </td>
            </tr>
        @endforeach
    </x-data-table>
</div>
@endsection
