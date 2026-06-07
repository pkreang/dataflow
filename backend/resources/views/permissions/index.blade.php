@extends('layouts.app')

@section('title', __('common.permissions'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.permissions')],
    ]" />
@endsection

@section('content')
@php $viewerIsSuperAdmin = (bool) session('user.is_super_admin', false); @endphp
    @if (session('success'))
        <div class="alert-success mb-4 text-sm text-green-800 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4 text-sm text-red-800 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-end gap-4 mb-6">
        <span class="text-sm text-slate-500 dark:text-slate-400">{{ $total }} {{ __('common.total') }}</span>
        @if($viewerIsSuperAdmin)
        <a href="{{ route('permissions.create') }}"
           class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_permission') }}
        </a>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($grouped as $module => $perms)
            <div class="card p-5">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-3">{{ \App\Support\PermissionDisplay::module($module) }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($perms as $perm)
                        @php
                            $action = $perm['action'] ?? '';
                            $inUse = $perm['in_use'] ?? false;
                            $colors = match($action) {
                                'create' => 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300',
                                'read' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300',
                                'update' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300',
                                'delete' => 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300',
                                'export' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300',
                                default => 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-300',
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1 py-1 rounded-full text-xs font-medium {{ $colors }}">
                            <span title="{{ $perm['name'] ?? '' }}">{{ \App\Support\PermissionDisplay::label($perm['name'] ?? '') }}</span>
                            @if($viewerIsSuperAdmin)
                            <a href="{{ route('permissions.edit', $perm['id']) }}"
                               class="shrink-0 p-0.5 rounded text-current opacity-80 hover:opacity-100 hover:bg-black/10 dark:hover:bg-white/10"
                               title="{{ __('common.edit') }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </a>
                            @if ($inUse)
                                <span class="shrink-0 p-0.5 rounded opacity-50 cursor-not-allowed"
                                      title="{{ __('common.permission_delete_disabled_in_use') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </span>
                            @else
                                <form method="POST" action="{{ route('permissions.destroy', $perm['id']) }}" class="inline shrink-0"
                                      onsubmit="return confirm(@json(__('common.permission_delete_confirm')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-0.5 rounded text-current opacity-80 hover:opacity-100 hover:bg-black/10 dark:hover:bg-white/10"
                                            title="{{ __('common.delete') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                            @endif {{-- viewerIsSuperAdmin --}}
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @if (empty($grouped))
        <div class="card p-12 text-center text-slate-500 dark:text-slate-400">
            {{ __('common.no_permissions_found') }}
        </div>
    @endif
@endsection
