@extends('layouts.app')

@section('title', __('common.create_permission'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.permissions'), 'url' => route('permissions.index')],
        ['label' => __('common.create_permission')],
    ]" />
@endsection

@section('content')
    <div>
        <div class="flex items-center justify-between gap-4 mb-6">
            <nav class="text-sm text-slate-500 dark:text-slate-400">
                <span>{{ __('common.settings') }}</span>
                <span class="mx-1">/</span>
                <a href="{{ route('permissions.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.permissions') }}</a>
            </nav>
            <a href="{{ route('permissions.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
        </div>

        <form method="POST" action="{{ route('permissions.store') }}"
              class="card p-6 space-y-5" novalidate>
            @csrf

            <div>
                <label for="name" class="form-label">
                    {{ __('common.permission_name') }} <span class="text-red-500" aria-hidden="true">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required autocomplete="off"
                       placeholder="module.action" aria-required="true"
                       class="form-input @error('name') form-input-error @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line">{{ __('common.permission_name_hint') }}</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('permissions.index') }}" class="btn-secondary">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection
