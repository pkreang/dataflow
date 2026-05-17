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

    <div class="rounded-xl border-l-4 border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 p-4 mb-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            <p class="text-xs text-emerald-800 dark:text-emerald-300">
                {{ __('common.evaluation_forms_banner') }}
            </p>
        </div>
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
