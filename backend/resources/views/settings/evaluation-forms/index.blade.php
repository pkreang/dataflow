@extends('layouts.app')

@section('title', __('common.evaluation_forms_index_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.evaluation_forms_index_title')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.evaluation_forms_index_title') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('common.evaluation_forms_index_desc') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $forms->count() }} {{ __('common.forms') }}</p>
        </div>
        <a href="{{ route('settings.evaluation-form.create') }}" class="btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif

    @if ($forms->isEmpty())
        <x-table-empty-state card :message="__('common.no_data')" />
    @else
        <x-data-table
            :columns="[
                ['key' => 'name', 'label' => __('common.name')],
                ['key' => 'form_key', 'label' => __('common.slug')],
                ['key' => 'fields_count', 'label' => __('common.evaluation_field_count')],
                ['key' => 'status', 'label' => __('common.status')],
                ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
            ]"
            :rows="$forms"
            :empty-message="__('common.no_data')"
            :disable-pagination="true"
        >
            @foreach ($forms as $form)
                @php $isDefault = $form->form_key === 'evaluation_default'; @endphp
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                    <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $form->name }}</p>
                            @if ($isDefault)
                                <span class="mob-badge tone-green" style="font-size:10px">{{ __('common.evaluation_form_default_badge') }}</span>
                            @endif
                        </div>
                        @if ($form->description)
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1">{{ $form->description }}</p>
                        @endif
                    </td>
                    <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                        <code class="text-xs text-slate-500 dark:text-slate-400">{{ $form->form_key }}</code>
                    </td>
                    <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-sm text-slate-500 dark:text-slate-400">
                        {{ $form->fields_count ?? 0 }}
                    </td>
                    <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                        @if ($form->is_active)
                            <span class="badge-green">{{ __('common.active') }}</span>
                        @else
                            <span class="badge-red">{{ __('common.inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-right">
                        <x-row-actions :items="array_filter([
                            ['label' => __('common.edit'), 'href' => route('settings.document-forms.edit', $form), 'icon' => 'edit'],
                            $isDefault ? null : ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.document-forms.destroy', $form), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $form->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                        ])" />
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @endif
</div>
@endsection
