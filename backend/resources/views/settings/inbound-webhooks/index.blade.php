@extends('layouts.app')

@section('title', __('common.incoming_webhooks'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.integrations')],
        ['label' => __('common.incoming_webhooks')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.incoming_webhooks') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('common.incoming_webhooks_subtitle') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $webhooks->total() }} {{ __('common.incoming_webhooks') }}</p>
        </div>
        <a href="{{ route('settings.inbound-webhooks.create') }}" class="btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add') }}
        </a>
    </div>

    <form method="GET" class="mb-5">
        <div class="relative max-w-sm">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('common.search') }}..." class="form-input" style="padding-left: 2.5rem;">
        </div>
    </form>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif

    @if ($webhooks->isEmpty())
        <x-table-empty-state card :message="__('common.no_data')" />
    @else
        <div class="table-wrapper">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="table-header">{{ __('common.name') }}</th>
                        <th class="table-header">{{ __('common.incoming_webhook_endpoint') }}</th>
                        <th class="table-header">{{ __('common.incoming_webhook_target_form') }}</th>
                        <th class="table-header">{{ __('common.incoming_webhook_received_count') }}</th>
                        <th class="table-header">{{ __('common.incoming_webhook_last_received') }}</th>
                        <th class="table-header">{{ __('common.status') }}</th>
                        <th class="table-header text-right">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($webhooks as $webhook)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M12 4v16"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $webhook->name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-xs font-mono text-slate-500 dark:text-slate-400 truncate max-w-xs">
                                /api/inbound/{{ $webhook->slug }}
                            </td>
                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $webhook->form?->name ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">
                                <span class="badge-blue">{{ $webhook->received_count }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                @if($webhook->last_received_at)
                                    {{ $webhook->last_received_at->format('d/m/Y H:i') }}
                                @else
                                    <span class="text-slate-400">{{ __('common.never') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                @if($webhook->is_active)
                                    <span class="badge-green">{{ __('common.active') }}</span>
                                @else
                                    <span class="badge-red">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-right">
                                <x-row-actions :items="[
                                    ['label' => __('common.edit'), 'href' => route('settings.inbound-webhooks.edit', $webhook), 'icon' => 'edit'],
                                    ['label' => __('common.delete'), 'method' => 'DELETE', 'action' => route('settings.inbound-webhooks.destroy', $webhook), 'icon' => 'delete', 'confirm' => __('common.delete_confirm_msg', ['name' => $webhook->name]), 'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'],
                                ]" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-per-page-footer :paginator="$webhooks" :perPage="$perPage" id="inbound-webhooks-pagination" />
    @endif
</div>
@endsection
