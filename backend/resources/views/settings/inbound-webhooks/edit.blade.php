@extends('layouts.app')

@section('title', __('common.incoming_webhook_edit'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.incoming_webhooks'), 'url' => route('settings.inbound-webhooks.index')],
        ['label' => $webhook->name],
    ]" />
@endsection

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $webhook->name }}</h2>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('common.incoming_webhook_received_count') }}: <span class="badge-blue">{{ $webhook->received_count }}</span>
            @if($webhook->last_received_at)
                · {{ __('common.incoming_webhook_last_received') }}: {{ $webhook->last_received_at->format('d/m/Y H:i') }}
            @endif
        </span>
    </div>

    <form method="POST" action="{{ route('settings.inbound-webhooks.update', $webhook) }}">
        @csrf
        @method('PUT')
        @include('settings.inbound-webhooks._form', [
            'webhook' => $webhook,
            'forms' => $forms,
            'suggestedToken' => null,
            'endpointUrl' => $endpointUrl,
        ])
    </form>

    <div class="mt-8 max-w-3xl" x-data="{ result: null, busy: false }">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">{{ __('common.incoming_webhook_test') }}</h3>
        <p class="text-xs text-slate-500 mb-3">Send a sample payload to this endpoint from the server side to verify it works end-to-end.</p>
        <button type="button"
                @click="busy = true; result = null;
                        fetch('{{ route('settings.inbound-webhooks.test', $webhook) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        })
                        .then(r => r.json())
                        .then(d => { result = d; busy = false; })
                        .catch(e => { result = { ok: false, error: String(e) }; busy = false; });"
                :disabled="busy"
                class="btn-secondary text-sm">
            <span x-text="busy ? 'Testing…' : '{{ __('common.incoming_webhook_test') }}'"></span>
        </button>

        <template x-if="result">
            <div class="mt-4 rounded-lg p-3 text-xs font-mono whitespace-pre-wrap"
                 :class="result.ok ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-900 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-900' : 'bg-red-50 dark:bg-red-900/20 text-red-900 dark:text-red-200 border border-red-200 dark:border-red-900'"
                 x-text="JSON.stringify(result, null, 2)"></div>
        </template>
    </div>
@endsection
