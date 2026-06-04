@extends('layouts.app')

@section('title', __('branding.title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.branding')],
    ]" />
@endsection

@section('content')
    <div class="w-full">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('branding.title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('branding.subtitle') }}</p>
        </div>

        @if (session('success'))
            <div class="alert-success mb-4">
                <p class="text-sm">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error mb-4">
                <ul class="text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.branding.save') }}" enctype="multipart/form-data"
              class="space-y-6" novalidate>
            @csrf

            {{-- System Logo --}}
            <div class="card p-6" x-data="{ preview: null }">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('branding.system_logo') }}</h3>
                @if ($systemLogo)
                    <div class="mb-4 flex items-center gap-4" x-show="!preview">
                        <img src="{{ asset('storage/' . $systemLogo) }}" alt="Logo" class="h-16 object-contain bg-white dark:bg-slate-700 rounded-lg p-2 border border-slate-200 dark:border-slate-600">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer">
                            <input type="hidden" name="remove_system_logo" value="0">
                            <input type="checkbox" name="remove_system_logo" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500 w-4 h-4">
                            {{ __('branding.remove_logo') }}
                        </label>
                    </div>
                @endif
                <div class="mb-4" x-show="preview" x-cloak>
                    <img :src="preview" alt="Preview" class="h-16 object-contain bg-white dark:bg-slate-700 rounded-lg p-2 border-2 border-blue-400 dark:border-blue-500">
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ __('branding.preview_new_image') }}</p>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('branding.system_logo_help') }}</p>
                <input type="file" name="system_logo" id="system_logo" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp,image/svg+xml"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block w-full text-sm text-slate-900 dark:text-slate-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-slate-600 dark:file:text-slate-200">
            </div>

            {{-- Login Background --}}
            <div class="card p-6" x-data="{ preview: null }">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('branding.login_background') }}</h3>
                @if ($loginBackground)
                    <div class="mb-4" x-show="!preview">
                        <img src="{{ asset('storage/' . $loginBackground) }}" alt="Background" class="max-h-32 rounded-lg border border-slate-200 dark:border-slate-600 object-cover">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer mt-2">
                            <input type="hidden" name="remove_login_background" value="0">
                            <input type="checkbox" name="remove_login_background" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500 w-4 h-4">
                            {{ __('branding.remove_background') }}
                        </label>
                    </div>
                @endif
                <div class="mb-4" x-show="preview" x-cloak>
                    <img :src="preview" alt="Preview" class="max-h-32 rounded-lg border-2 border-blue-400 dark:border-blue-500 object-cover">
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ __('branding.preview_new_image') }}</p>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('branding.login_background_help') }}</p>
                <input type="file" name="login_background" id="login_background" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block w-full text-sm text-slate-900 dark:text-slate-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-slate-600 dark:file:text-slate-200">
            </div>

            {{-- Login Illustration --}}
            <div class="card p-6" x-data="{ preview: null }">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('branding.login_illustration') }}</h3>
                @if ($loginIllustration ?? null)
                    <div class="mb-4" x-show="!preview">
                        <img src="{{ asset('storage/' . $loginIllustration) }}" alt="Illustration" class="max-h-40 rounded-lg border border-slate-200 dark:border-slate-600 object-contain">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer mt-2">
                            <input type="hidden" name="remove_login_illustration" value="0">
                            <input type="checkbox" name="remove_login_illustration" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500 w-4 h-4">
                            {{ __('branding.remove_login_illustration') }}
                        </label>
                    </div>
                @endif
                <div class="mb-4" x-show="preview" x-cloak>
                    <img :src="preview" alt="Preview" class="max-h-40 rounded-lg border-2 border-blue-400 dark:border-blue-500 object-contain">
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">{{ __('branding.preview_new_image') }}</p>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('branding.login_illustration_help') }}</p>
                <input type="file" name="login_illustration" id="login_illustration" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block w-full text-sm text-slate-900 dark:text-slate-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-slate-600 dark:file:text-slate-200">
            </div>

            {{-- Login Background Color --}}
            <div class="card p-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('branding.login_background_color') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('branding.login_background_color_help') }}</p>
                <div class="flex flex-wrap items-center gap-3" x-data="{ color: '{{ old('login_background_color', $loginBackgroundColor) }}' }">
                    <input type="color" :value="color" @input="color = $event.target.value"
                           class="h-10 w-14 rounded border border-slate-300 dark:border-slate-600 cursor-pointer bg-white dark:bg-slate-700">
                    <input type="text" name="login_background_color" x-model="color"
                           class="form-input w-28"
                           placeholder="#2563eb">
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('dashboard') }}" class="btn-secondary">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection
