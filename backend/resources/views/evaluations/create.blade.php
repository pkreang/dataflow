@extends($layout ?? 'layouts.app')

@section('title', __('common.action_evaluate'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => $parent->reference_no ?: '#'.$parent->id, 'url' => route('forms.submission.show', $parent)],
        ['label' => __('common.action_evaluate')],
    ]" />
@endsection

@section('content')
<div style="width:100%;max-width:100%">
    <div class="mb-6">
        <a href="{{ route('forms.submission.show', $parent) }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ __('common.evaluation_cta_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ __('common.evaluation_cta_desc') }}
        </p>
    </div>

    {{-- Parent submission summary --}}
    <div class="card p-4 mb-4 bg-slate-50 dark:bg-slate-800/50">
        <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
            {{ $parent->reference_no ?: '#'.$parent->id }}
        </p>
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 mt-1">
            {{ $parent->form?->name ?? '—' }}
        </p>
    </div>

    @if($errors->any())
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $storeAction }}" novalidate class="w-full"
          x-data="dynamicForm({})">
        @csrf
        <div class="card p-4 sm:p-6">
            <x-document-form-fields-grid :columns="1" class="gap-x-6 gap-y-5">
                @foreach($form->fields as $field)
                    @php
                        $fKey   = $field->field_key;
                        $fName  = "fields[{$fKey}]";
                        $fValue = old("fields.{$fKey}", '');
                    @endphp
                    <div>
                        <label class="form-label">
                            {{ $field->localized_label }}
                            @if($field->is_required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        @include('components.dynamic-field', [
                            'field'        => $field,
                            'name'         => $fName,
                            'value'        => $fValue,
                            'editorRole'   => 'requester',
                            'editorUserId' => (int) (session('user.id') ?? 0) ?: null,
                        ])
                    </div>
                @endforeach
            </x-document-form-fields-grid>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('forms.submission.show', $parent) }}" class="btn-secondary">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn-primary">{{ __('common.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
