@extends('layouts.app')

@section('title', __('users.import_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.user_and_access'), 'url' => route('users.index')],
        ['label' => __('common.import_data')],
    ]" />
@endsection

@section('content')
<div>
    <nav class="text-sm text-slate-500 dark:text-slate-400 mb-2">
        <a href="{{ route('users.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.users') }}</a>
        <span class="mx-1">/</span>
        <span>{{ __('common.import') }}</span>
    </nav>
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ __('users.import_title') }}</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ __('users.import_subtitle') }}</p>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('import_errors'))
        <div class="alert-warning mb-4">
            <p class="text-sm font-medium mb-2">{{ __('common.error') }}</p>
            <ul class="text-sm space-y-1 list-disc list-inside">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-6">
            <form method="POST" action="{{ route('users.import.store') }}" enctype="multipart/form-data" class="space-y-5" novalidate>
                @csrf
                <div>
                    <label class="form-label">
                        {{ __('users.import_upload_label') }}
                    </label>
                    <input type="file" name="file" accept=".csv,.txt" required
                           class="w-full text-sm text-slate-600 dark:text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 dark:file:bg-blue-900/30 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50" />
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ __('users.import_upload_hint') }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('users.index') }}" class="btn-secondary">
                        {{ __('common.cancel') }}
                    </a>
                    <button type="submit" class="btn-primary">
                        {{ __('common.import') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ __('users.import_template_title') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">{{ __('users.import_template_hint') }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="table-header py-2 pr-4 text-left">Column</th>
                            <th class="table-header py-2 text-left">Required</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-700 dark:text-slate-300">
                        <tr><td class="py-1.5 pr-4 font-mono">email</td><td>*</td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">first_name</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">last_name</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">department</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">position</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">org_unit</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">phone</td><td></td></tr>
                        <tr><td class="py-1.5 pr-4 font-mono">remark</td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
