@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.document_forms'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_forms'), 'url' => route('settings.document-forms.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
    @if (($form->form_key ?? null) === 'evaluation_default')
        <div class="rounded-xl border-l-4 border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 p-4 mb-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-200 mb-1">
                        {{ __('common.evaluation_form_help_title') }}
                    </h3>
                    <p class="text-xs text-emerald-800 dark:text-emerald-300 mb-2">
                        {{ __('common.evaluation_form_help_desc') }}
                    </p>
                    <ol class="text-xs text-emerald-800 dark:text-emerald-300 list-decimal list-inside space-y-1">
                        <li>{{ __('common.evaluation_form_help_step1') }}</li>
                        <li>{{ __('common.evaluation_form_help_step2') }}</li>
                        <li>{{ __('common.evaluation_form_help_step3') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    @endif

    @include('settings.document-forms._form', ['inlineToolbar' => true])
@endsection
