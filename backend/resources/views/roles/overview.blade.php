@extends('layouts.app')

@section('title', __('common.rbac_overview'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.roles'), 'url' => route('roles.index')],
        ['label' => __('common.rbac_overview')],
    ]" />
@endsection

@section('content')
<div x-data="{ q: '' }">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="page-title">{{ __('common.rbac_overview') }}</h2>
        <a href="{{ route('roles.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
    </div>

    <div class="max-w-sm mb-4">
        <input type="text" x-model="q" placeholder="{{ __('common.rbac_search_placeholder') }}" class="form-input w-full">
    </div>

    <div class="table-wrapper">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="table-header text-left">{{ __('common.permission') }}</th>
                    @foreach ($roles as $role)
                        <th class="table-header text-center whitespace-nowrap">
                            <a href="{{ route('roles.edit', $role->id) }}"
                               class="text-blue-600 dark:text-blue-400 hover:underline">{{ $role->name }}</a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($grouped as $module => $perms)
                    <tr class="bg-slate-50 dark:bg-slate-800/40">
                        <td colspan="{{ count($roles) + 1 }}"
                            class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ \App\Support\PermissionDisplay::module($module) }}
                        </td>
                    </tr>
                    @foreach ($perms as $perm)
                        @php $label = \App\Support\PermissionDisplay::label($perm['name'] ?? ''); @endphp
                        <tr data-search="{{ \Illuminate\Support\Str::lower($label.' '.($perm['name'] ?? '')) }}"
                            x-show="!q || ($el.dataset.search || '').includes(q.toLowerCase())">
                            <td class="px-4 py-2">
                                <div class="text-sm text-slate-800 dark:text-slate-200">{{ $label }}</div>
                                <div class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ $perm['name'] ?? '' }}</div>
                            </td>
                            @foreach ($roles as $role)
                                <td class="px-4 py-2 text-center">
                                    @if (in_array($perm['id'], $rolePermissionIds[$role->id] ?? [], true))
                                        <span class="text-green-600 dark:text-green-400 font-semibold" title="{{ $role->name }}">✓</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">·</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="{{ count($roles) + 1 }}" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('common.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ __('common.rbac_overview_note') }}</p>
</div>
@endsection
