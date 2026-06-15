@extends('layouts.app')

@section('title', __('common.edit_role') . ': ' . ($role['name'] ?? ''))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.roles'), 'url' => route('roles.index')],
        ['label' => __('common.edit_role')],
    ]" />
@endsection

@section('content')
    <div class="max-w-4xl">
        <div class="flex justify-end mb-6">
            <a href="{{ route('roles.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
        </div>

        @if ($errors->any())
            <div class="alert-error mb-4">
                <p class="text-sm text-red-700 dark:text-red-400">{{ $errors->first() }}</p>
            </div>
        @endif

        @php
            $assignedIds = collect($role['permissions'] ?? [])->pluck('id')->toArray();
            $totalPermissions = collect($grouped)->sum(fn ($p) => count($p));
        @endphp

        <form method="POST" action="{{ route('roles.update', $role['id']) }}" class="card p-6 space-y-6" novalidate>
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="form-label">{{ __('common.role_name') }}</label>
                <input type="text" name="name" id="name" value="{{ old('name', $role['name'] ?? '') }}" required
                       class="form-input max-w-md">
            </div>

            <div x-data="{
                    total: {{ $totalPermissions }},
                    count: 0,
                    recount() { this.count = this.$root.querySelectorAll('input.perm-check:checked').length },
                    setGroup(el, val) {
                        el.closest('[data-perm-group]').querySelectorAll('input.perm-check').forEach(c => c.checked = val);
                        this.recount();
                    }
                 }" x-init="recount()">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('common.permissions_title') }}</h3>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('common.rbac_role_grants') }}:
                        <span class="font-semibold text-blue-600 dark:text-blue-400" x-text="count"></span>
                        / <span x-text="total"></span>
                    </span>
                </div>
                <div class="space-y-4">
                    @foreach ($grouped as $module => $perms)
                        <div data-perm-group class="border border-slate-200 dark:border-slate-700 rounded-lg p-4" x-data="{ expanded: true }">
                            <div class="flex items-center justify-between gap-3">
                                <button type="button" @click="expanded = !expanded" class="flex items-center gap-2 text-left">
                                    <svg :class="{ 'rotate-180': expanded }" class="w-4 h-4 text-slate-400 dark:text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ \App\Support\PermissionDisplay::module($module) }}</span>
                                </button>
                                <div class="flex items-center gap-2 shrink-0 text-xs">
                                    <button type="button" @click="setGroup($el, true)" class="text-blue-600 dark:text-blue-400 hover:underline">{{ __('common.select_all') }}</button>
                                    <span class="text-slate-300 dark:text-slate-600">|</span>
                                    <button type="button" @click="setGroup($el, false)" class="text-slate-500 dark:text-slate-400 hover:underline">{{ __('common.clear_all') }}</button>
                                </div>
                            </div>
                            <div x-show="expanded" class="mt-3 grid sm:grid-cols-2 gap-2">
                                @foreach ($perms as $perm)
                                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm['id'] }}"
                                               @change="recount()"
                                               {{ in_array($perm['id'], old('permissions', $assignedIds)) ? 'checked' : '' }}
                                               class="perm-check mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="min-w-0">
                                            <span class="block text-slate-700 dark:text-slate-300">{{ \App\Support\PermissionDisplay::label($perm['name'] ?? '') }}</span>
                                            <span class="block text-xs font-mono text-slate-400 dark:text-slate-500 truncate">{{ $perm['name'] ?? '' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-4 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('roles.index') }}" class="btn-secondary">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection
