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
        <a href="{{ route('forms.my-submissions') }}"
           class="btn-secondary">
            {{ __('common.my_submissions') }}
        </a>
    </div>

    @if($forms->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-slate-500 dark:text-slate-400 text-sm">{{ __('common.no_forms_available') }}</p>
        </div>
    @else
        <div class="space-y-8">
            @foreach($forms as $docType => $group)
                @php($docTypeModel = \App\Models\DocumentType::resolveByCode($docType))
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                        @if ($docTypeModel?->icon)
                            <x-nav-icon :name="$docTypeModel->icon" class="w-5 h-5" />
                        @endif
                        <span>{{ $docTypeModel?->label() ?? $docType }}</span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($group as $form)
                            <div class="card p-5 flex flex-col gap-3">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-slate-900 dark:text-slate-100">{{ $form->name }}</h4>
                                    @if($form->description)
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $form->description }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('forms.create', $form->form_key) }}"
                                   class="btn-primary text-center">
                                    {{ __('common.fill_form') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
