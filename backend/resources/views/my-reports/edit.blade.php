@extends('layouts.app')

@section('title', __('common.my_reports_edit'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.my_reports'), 'url' => route('my-reports.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.my_reports_edit') }}: {{ $dashboard->name }}</h2>
        <a href="{{ route('my-reports.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>
    <div class="card p-6">
        @include('my-reports._form')
    </div>
@endsection
