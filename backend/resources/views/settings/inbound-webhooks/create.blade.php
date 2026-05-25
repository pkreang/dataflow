@extends('layouts.app')

@section('title', __('common.incoming_webhook_new'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.incoming_webhooks'), 'url' => route('settings.inbound-webhooks.index')],
        ['label' => __('common.add')],
    ]" />
@endsection

@section('content')
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">{{ __('common.incoming_webhook_new') }}</h2>

    <form method="POST" action="{{ route('settings.inbound-webhooks.store') }}">
        @csrf
        @include('settings.inbound-webhooks._form', [
            'webhook' => null,
            'forms' => $forms,
            'suggestedToken' => $suggestedToken,
            'endpointUrl' => null,
        ])
    </form>
@endsection
