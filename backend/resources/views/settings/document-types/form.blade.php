@extends('layouts.app')

@php
    $isEdit = isset($documentType);
@endphp

@section('title', $isEdit ? __('common.edit_document_type') : __('common.add_document_type'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.document_types'), 'url' => route('settings.document-types.index')],
        ['label' => $isEdit ? __('common.edit') : __('common.add')],
    ]" />
@endsection

@section('content')
<div>
    <nav class="text-sm text-slate-500 dark:text-slate-400 mb-2">
        <span>{{ __('common.settings') }}</span>
        <span class="mx-1">/</span>
        <a href="{{ route('settings.document-types.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.document_types') }}</a>
    </nav>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
            {{ $isEdit ? __('common.edit_document_type') : __('common.add_document_type') }}
        </h2>
        <a href="{{ route('settings.document-types.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('settings.document-types.update', $documentType) : route('settings.document-types.store') }}" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="card p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">
                        {{ __('common.code') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="code" value="{{ old('code', $documentType->code ?? '') }}" required maxlength="100"
                           class="form-input @error('code') form-input-error @enderror" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('common.code_snake_case_hint') }}</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label">
                        {{ __('common.label') }} (EN) <span class="text-red-500">*</span>
                    </label>
                    <input name="label_en" value="{{ old('label_en', $documentType->label_en ?? '') }}" required maxlength="255"
                           class="form-input @error('label_en') form-input-error @enderror" />
                    @error('label_en')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label">
                        {{ __('common.label') }} (TH) <span class="text-red-500">*</span>
                    </label>
                    <input name="label_th" value="{{ old('label_th', $documentType->label_th ?? '') }}" required maxlength="255"
                           class="form-input @error('label_th') form-input-error @enderror" />
                    @error('label_th')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label">{{ __('common.sort_order') }}</label>
                    <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $documentType->sort_order ?? 0) }}"
                           class="form-input" />
                </div>
                <div class="flex items-end pb-1">
                    <x-form.active-toggle
                        name="is_active"
                        :checked="old('is_active', $documentType->is_active ?? true)"
                        label-class="form-label" />
                </div>
                <div class="md:col-span-2" x-data="{ icon: @js(old('icon', $documentType->icon ?? '')) }">
                    <label class="form-label">{{ __('common.icon') }}</label>
                    <input type="hidden" name="icon" :value="icon">
                    <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 gap-2 p-3 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800/40 @error('icon') border-red-400 dark:border-red-500 @enderror">
                        <button type="button" @click="icon = ''"
                                :class="icon === '' ? 'ring-2 ring-blue-500 bg-white dark:bg-slate-700' : ''"
                                title="{{ __('common.none') }}"
                                class="aspect-square flex items-center justify-center text-slate-400 hover:bg-white dark:hover:bg-slate-700 rounded">
                            <span class="text-lg leading-none">&mdash;</span>
                        </button>
                        @foreach(\App\Support\IconCatalog::names() as $iconName)
                            <button type="button" @click="icon = @js($iconName)"
                                    :class="icon === @js($iconName) ? 'ring-2 ring-blue-500 bg-white dark:bg-slate-700' : ''"
                                    title="{{ $iconName }}"
                                    class="aspect-square flex items-center justify-center text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 rounded">
                                <x-nav-icon :name="$iconName" class="w-5 h-5" />
                            </button>
                        @endforeach
                    </div>
                    @error('icon')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('common.description') }}</label>
                    <textarea name="description" rows="2" maxlength="1000" class="form-input resize-y">{{ old('description', $documentType->description ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end pt-2 pb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('settings.document-types.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
