@extends('layouts.app')

@section('title', 'PoC Form: '.$table)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'PoC Schema-first'],
        ['label' => $table],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                PoC Form — <code class="text-blue-600 dark:text-blue-400">{{ $table }}</code>
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Schema-first PoC. Form rendered from <code>{{ $table }}</code> + annotations.
            </p>
        </div>
        <div>
            <a href="{{ route('poc.schema-first.annotate', $table) }}" class="btn-secondary">Edit annotations</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="alert-error mb-4">
            <p class="font-semibold">{{ __('common.validation_error') ?? 'Please fix the errors below' }}:</p>
            <ul class="list-disc ml-5 text-sm mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($fields->isEmpty())
        <div class="alert-warning">
            No visible columns. Run <code>php artisan poc:annotate {{ $table }}</code> or
            <a href="{{ route('poc.schema-first.annotate', $table) }}" class="underline">edit annotations</a>.
        </div>
    @else
        <form method="POST" action="{{ route('poc.schema-first.submit', $table) }}" enctype="multipart/form-data"
              class="space-y-4 max-w-3xl">
            @csrf
            @foreach ($fields as $field)
                @php
                    $name = $field->field_type === 'multi_select' ? $field->field_key.'[]' : $field->field_key;
                    $value = old($field->field_key);
                @endphp
                <div>
                    @if ($field->field_type !== 'section')
                        <label for="field_{{ $field->field_key }}" class="form-label">
                            {{ $field->label }}
                            @if ($field->is_required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                    @endif
                    @include('components.dynamic-field', [
                        'field' => $field,
                        'name' => $name,
                        'value' => $value,
                        'userOrgUnitId' => null,
                        'editorRole' => 'requester',
                    ])
                    @error($field->field_key)
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                <button type="submit" class="btn-primary">Submit</button>
                <a href="{{ url()->current() }}" class="btn-secondary ml-2">Reset</a>
            </div>
        </form>
    @endif
</div>
@endsection
