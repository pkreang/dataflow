@extends('layouts.app')

@section('title', __('common.roles'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.roles')],
    ]" />
@endsection

@section('content')
@php $viewerIsSuperAdmin = (bool) session('user.is_super_admin', false); @endphp
    <div class="flex items-center justify-between mb-6 gap-2">
        <h2 class="page-title">{{ __('common.all_roles') }}</h2>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('roles.overview') }}" class="btn-secondary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                {{ __('common.rbac_overview_button') }}
            </a>
            @if($viewerIsSuperAdmin)
            <a href="{{ route('roles.create') }}" class="btn-primary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add_role') }}
            </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
        </div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'role', 'label' => __('common.role')],
            ['key' => 'permissions', 'label' => __('common.permissions')],
            ['key' => 'users', 'label' => __('common.users')],
            ['key' => 'created_at', 'label' => __('common.created_at')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$roles"
        :empty-message="__('common.no_roles_found')"
        :empty-cta-href="route('roles.create')"
        :empty-cta-label="__('common.add_role')"
        :disable-pagination="true"
    >
        @foreach ($roles as $role)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-6 py-3 whitespace-nowrap">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $role['name'] ?? '' }}</p>
                </td>
                <td class="table-sub">{{ $role['permissions_count'] ?? 0 }}</td>
                <td class="table-sub">{{ $role['users_count'] ?? 0 }}</td>
                <td class="table-sub">
                    {{ isset($role['created_at']) ? \Carbon\Carbon::parse($role['created_at'])->format('M d, Y') : '-' }}
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-right">
                    @if($viewerIsSuperAdmin)
                    <div x-data="{ open: false }" class="relative inline-block">
                        <button @click="open = !open" type="button" class="table-action-btn" x-ref="trigger">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                            </svg>
                        </button>
                        <template x-teleport="body">
                            <div x-show="open"
                                 x-anchor.bottom-end.offset.8="$refs.trigger"
                                 @click.outside="open = false"
                                 @keydown.escape.window="open = false"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="w-40 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-[200]">
                                <a href="{{ route('roles.edit', $role['id']) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    {{ __('common.edit') }}
                                </a>
                                <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                <button @click="open = false;
                                                $dispatch('open-delete-modal', {
                                                    id: {{ $role['id'] }},
                                                    name: {{ json_encode($role['display_name'] ?? $role['name'] ?? '') }}
                                                })"
                                        class="w-full flex items-center gap-2 px-4 py-2 text-sm text-left text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    {{ __('common.delete') }}
                                </button>
                            </div>
                        </template>
                    </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$roles" :perPage="$perPage" id="roles-pagination" />

    @if($viewerIsSuperAdmin)
    {{-- Delete Confirm Modal --}}
    <div x-data="{ show: false, id: null, name: '' }"
         @open-delete-modal.window="show = true; id = $event.detail.id; name = $event.detail.name"
         x-show="show" x-cloak x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div @click.outside="show = false"
             class="bg-white dark:bg-slate-800 rounded-2xl p-6 w-80 shadow-xl text-center">
            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ __('common.confirm_delete') }}
            </h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('common.delete') }} <strong x-text="name"></strong>?
                <br><span class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.cannot_undo') }}</span>
            </p>
            <div class="flex gap-2 mt-4">
                <button @click="show = false" class="btn-secondary flex-1">
                    {{ __('common.cancel') }}
                </button>
                <form x-bind:action="`{{ url('roles') }}/${id}`" method="POST" class="flex-1" x-show="id" novalidate>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger w-full">
                        {{ __('common.delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif
@endsection
