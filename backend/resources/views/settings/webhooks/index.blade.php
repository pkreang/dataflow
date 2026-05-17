@extends('layouts.app')

@section('title', __('common.integrations'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.integrations')],
    ]" />
@endsection

@section('content')
<div x-data="{ search: '' }">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.integrations') }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('common.webhooks_subtitle') }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $webhooks->total() }} {{ __('common.webhooks') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.webhooks.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add') }}
            </a>
        </div>
    </div>

    <div class="mb-5">
        <div class="relative max-w-sm">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" x-model="search" placeholder="{{ __('common.search') }}..." class="form-input" style="padding-left: 2.5rem;">
        </div>
    </div>

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
                    <th class="table-header">URL</th>
                    <th class="table-header">{{ __('common.events') }}</th>
                    <th class="table-header">{{ __('common.last_triggered') }}</th>
                    <th class="table-header">{{ __('common.status') }}</th>
                    <th class="table-header text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($webhooks as $webhook)
                    @php
                        $searchBlob = Str::lower($webhook->name . ' ' . $webhook->url);
                        $eventCount = is_array($webhook->events) ? count($webhook->events) : 0;
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                        data-search="{{ e($searchBlob) }}"
                        x-show="!search.trim() || ($el.dataset.search || '').includes(search.toLowerCase())">
                        <td class="px-6 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $webhook->name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-sm font-mono text-slate-500 dark:text-slate-400 truncate max-w-xs">
                            {{ Str::limit($webhook->url, 50) }}
                        </td>
                        <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">
                            @if($eventCount > 0)
                                <span class="badge-blue">{{ $eventCount }} {{ __('common.events_short') }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            @if($webhook->last_triggered_at)
                                <div>{{ $webhook->last_triggered_at->format('d/m/Y H:i') }}</div>
                                @if($webhook->last_response_status)
                                    <span class="text-xs {{ $webhook->last_response_status < 400 ? 'text-emerald-600' : 'text-red-600' }}">
                                        HTTP {{ $webhook->last_response_status }}
                                    </span>
                                @endif
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
                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                <button @click="open = !open" type="button" class="table-action-btn">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak
                                     class="absolute right-0 bottom-full mb-2 w-44 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50">
                                    <a href="{{ route('settings.webhooks.edit', $webhook) }}"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                                        {{ __('common.edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('settings.webhooks.destroy', $webhook) }}"
                                          onsubmit="return confirm('{{ __('common.delete_confirm_msg', ['name' => $webhook->name]) }}')" novalidate>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                                            {{ __('common.delete') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-per-page-footer :paginator="$webhooks" :perPage="$perPage" id="webhooks-pagination" />
    @endif
</div>
@endsection
