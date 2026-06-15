@extends('layouts.app')

@section('title', __('common.webhook_edit'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.integrations'), 'url' => route('settings.webhooks.index')],
        ['label' => __('common.edit')],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.webhook_edit') }}: {{ $webhook->name }}</h2>
        <a href="{{ route('settings.webhooks.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
    </div>
    <div class="card p-6">
        @include('settings.webhooks._form')
    </div>
@endsection
