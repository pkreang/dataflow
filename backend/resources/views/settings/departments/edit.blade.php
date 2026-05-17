@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.departments'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.departments'), 'url' => route('settings.departments.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between gap-4 mb-6">
        <nav class="text-sm text-slate-500 dark:text-slate-400">
            <span>{{ __('common.settings') }}</span>
            <span class="mx-1">/</span>
            <a href="{{ route('settings.departments.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.departments') }}</a>
        </nav>
        <a href="{{ route('settings.departments.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4">
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-5">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ __('common.edit') }} {{ __('common.departments') }}</h2>
            <form method="POST" action="{{ route('settings.departments.update', $department) }}" class="space-y-3" novalidate>
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label">{{ __('common.system_code') }}</label>
                    <input type="text" value="{{ $department->auto_code }}" disabled
                           class="form-input mt-1 bg-slate-50 dark:bg-slate-800 text-slate-500 font-mono cursor-not-allowed" />
                    <p class="mt-1 text-xs text-slate-400">{{ __('common.system_code_immutable_hint') }}</p>
                </div>
                <div>
                    <label class="form-label">{{ __('common.code') }}</label>
                    <input name="code" value="{{ $department->code }}" required class="form-input mt-1" />
                </div>
                <div>
                    <label class="form-label">{{ __('common.name') }}</label>
                    <input name="name" value="{{ $department->name }}" required class="form-input mt-1" />
                </div>
                <div>
                    <label class="form-label">{{ __('common.remark') }}</label>
                    <textarea name="description" rows="3" class="form-input mt-1 resize-y">{{ $department->description }}</textarea>
                </div>
                <button class="btn-primary">{{ __('common.save') }}</button>
            </form>
        </div>

        <div class="card p-5">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ __('common.workflow_binding') }}</h3>
            <div class="space-y-3">
                @forelse($documentTypes as $docType)
                    @php
                        $currentBinding = $department->workflowBindings->firstWhere('document_type', $docType);
                        $docLabel = \App\Models\DocumentType::allActive()->firstWhere('code', $docType)?->label()
                            ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $docType));
                        $options = $workflows->where('document_type', $docType);
                    @endphp
                    <div class="space-y-2">
                        <label class="text-xs text-slate-500">{{ $docLabel }}</label>
                        @if ($options->isEmpty())
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.no_workflows_for_document_type') }}</p>
                        @else
                            <form method="POST" action="{{ route('settings.departments.bindings.store', $department) }}" class="flex items-center gap-2" novalidate>
                                @csrf
                                <input type="hidden" name="document_type" value="{{ $docType }}">
                                <select name="workflow_id" class="form-input flex-1">
                                    @foreach ($options as $workflow)
                                        <option value="{{ $workflow->id }}" @selected(optional($currentBinding)->workflow_id === $workflow->id)>{{ $workflow->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-primary text-xs px-3 py-2">{{ __('common.save') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.department_workflow_bindings_no_types') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
