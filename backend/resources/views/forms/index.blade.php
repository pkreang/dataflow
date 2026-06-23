@extends('layouts.app')

@section('title', __('common.forms_index_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title')],
    ]" />
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.forms_index_title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.forms_index_desc') }}</p>
        </div>
    </div>

    @if($forms->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-slate-500 dark:text-slate-400 text-sm">{{ __('common.no_forms_available') }}</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($forms as $docType => $group)
                @php($docTypeModel = \App\Models\DocumentType::resolveByCode($docType))
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                        @if ($docTypeModel?->icon)
                            <x-nav-icon :name="$docTypeModel->icon" class="w-5 h-5" />
                        @endif
                        <span>{{ $docTypeModel?->label() ?? $docType }}</span>
                    </h3>
                    <x-data-table
                        :columns="[
                            ['key' => 'name', 'label' => __('common.name')],
                            ['key' => 'status', 'label' => __('common.status')],
                            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
                        ]"
                        :rows="$group"
                        :empty-message="__('common.no_forms_available')"
                    >
                        @foreach($group as $form)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $form->name }}</p>
                                    @if($form->description)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $form->description }}</p>
                                    @endif
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                                    <x-status-badge :status="$form->is_active ? 'active' : 'inactive'" />
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-right">
                                    <x-row-actions :items="[
                                        ['label' => __('common.fill_form'), 'href' => route('forms.create', $form->form_key), 'icon' => 'edit'],
                                        ['label' => __('common.view_submissions'), 'href' => route('forms.list-by-form', $form->form_key), 'icon' => 'view'],
                                    ]" />
                                </td>
                            </tr>
                        @endforeach
                    </x-data-table>
                </div>
            @endforeach
        </div>
    @endif
@endsection
