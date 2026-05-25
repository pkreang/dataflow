@extends('layouts.app')

@section('title', $lookup->label_en)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.lookups'), 'url' => route('settings.lookups.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
<div>
    <a href="{{ route('settings.lookups.index') }}" class="text-sm text-blue-600 hover:underline">&larr; {{ __('common.lookups') }}</a>
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2 mb-4">
        {{ app()->getLocale() === 'th' ? $lookup->label_th : $lookup->label_en }}
        @if($lookup->is_system)
            <span class="badge-gray ml-2 text-xs">{{ __('common.lookup_is_system') }}</span>
        @endif
    </h2>

    {{-- Bulk CSV import/export --}}
    <div class="card p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('common.lookup_bulk_csv') }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ __('common.lookup_bulk_csv_desc') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('settings.lookups.export', $lookup) }}" class="btn-secondary text-sm">
                {{ __('common.action_download_csv') }}
            </a>
            <form method="POST" action="{{ route('settings.lookups.import', $lookup) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" class="text-xs" required>
                <select name="mode" class="form-input text-xs py-1">
                    <option value="replace">{{ __('common.lookup_import_replace') }}</option>
                    <option value="append">{{ __('common.lookup_import_append') }}</option>
                </select>
                <button type="submit" class="btn-primary text-sm">{{ __('common.lookup_import') }}</button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.lookups.update', $lookup) }}">
        @csrf
        @method('PUT')
        @include('settings.lookups._form', ['lookup' => $lookup])
    </form>
</div>
@endsection
