@extends('layouts.app')

@section('title', __('common.add') . ' ' . __('common.departments'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.departments'), 'url' => route('settings.departments.index')],
        ['label' => __('common.add')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between gap-4 mb-6">
        <nav class="text-sm text-slate-500 dark:text-slate-400">
            <span>{{ __('common.settings') }}</span>
            <span class="mx-1">/</span>
            <a href="{{ route('settings.departments.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.departments') }}</a>
        </nav>
        <a href="{{ route('settings.departments.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.departments.store') }}" novalidate>
        @csrf
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('common.departments') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">
                        {{ __('common.code') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="code" value="{{ old('code') }}" required maxlength="100"
                           class="form-input @error('code') form-input-error @enderror" />
                    @error('code')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">
                        {{ __('common.name') }} <span class="text-red-500">*</span>
                    </label>
                    <input name="name" value="{{ old('name') }}" required maxlength="255"
                           class="form-input @error('name') form-input-error @enderror" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">
                        {{ __('common.remark') }}
                    </label>
                    <textarea name="description" rows="2" maxlength="1000"
                              class="form-input resize-y @error('description') form-input-error @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <x-form.active-toggle name="is_active" :checked="old('is_active', true)" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 pt-2 pb-4">
            <a href="{{ route('settings.departments.index') }}" class="btn-secondary">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn-primary">
                {{ __('common.save') }}
            </button>
        </div>
    </form>
</div>
@endsection
