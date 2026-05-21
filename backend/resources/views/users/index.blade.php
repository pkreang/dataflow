@extends('layouts.app')

@section('title', __('common.users'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.users')],
    ]" />
@endsection

@section('content')
<div x-data="userIndex({{ json_encode(request('search', '')) }})">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.all_users') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $totalUsers }} {{ Str::plural('user', $totalUsers) }} total</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('users.import') }}" class="btn-secondary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                {{ __('common.import_data') }}
            </a>
            <a href="{{ route('users.create') }}" class="btn-primary inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('common.add_user') }}
            </a>
        </div>
    </div>

    {{-- Search (AJAX — Alpine query model + loading spinner) --}}
    <div class="mb-5">
        <x-search-bar mode="ajax" name="query" :placeholder="__('common.search_placeholder')" />
    </div>

    @if (session('error'))
        <div class="alert-error mb-4">
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <div id="users-table" x-ref="usersTable" class="table-wrapper">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.user') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.departments') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.positions') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.roles') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.status') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.last_active') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('users.phone') }}</th>
                    <th class="table-header px-6 py-3 text-right">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody id="users-tbody-data" class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse ($users as $user)
                    @php
                        $fullName = $user->full_name;
                        $initials = strtoupper(
                            mb_substr($user->first_name ?? '', 0, 1) . mb_substr($user->last_name ?? '', 0, 1)
                        ) ?: '??';

                        $avatarColors = [
                            'bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500',
                            'bg-rose-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-pink-500',
                            'bg-teal-500', 'bg-orange-500',
                        ];
                        $colorIndex = crc32($fullName) % count($avatarColors);
                        $avatarBg = $avatarColors[abs($colorIndex)];

                        $lastActive = $user->last_active_at;
                        $lastActiveText = $lastActive ? $lastActive->diffForHumans() : 'Never';

                        $isSuperAdmin = $user->is_super_admin ?? false;
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                        <td class="px-6 py-3 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full {{ $avatarBg }} flex items-center justify-center shrink-0">
                                    <span class="text-xs font-semibold text-white leading-none">{{ $initials }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ $fullName }}</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ $user->email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                            {{ $user->department?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                            {{ $user->jobPosition?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @foreach ($user->roles as $role)
                                <span class="badge-blue mr-1">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if ($user->is_active ?? true)
                                <span class="badge-green">{{ __('common.active') }}</span>
                            @else
                                <span class="badge-red">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $lastActiveText }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $user->phone ?? '-' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-right">
                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                <button @click="open = !open" type="button"
                                        class="table-action-btn">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                    </svg>
                                </button>

                                <div x-show="open" @click.outside="open = false" x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 bottom-full mb-2 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50">
                                    <a href="{{ route('users.edit', $user->id) }}"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        {{ __('common.edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('users.update', $user->id) }}" class="block" novalidate>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="toggle_active" value="1">
                                        <button type="submit"
                                                class="flex items-center gap-2 w-full px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                                            @if ($user->is_active ?? true)
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                {{ __('common.disable') }}
                                            @else
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('common.enable') }}
                                            @endif
                                        </button>
                                    </form>
                                    @if (!$isSuperAdmin)
                                        <div class="border-t border-slate-100 my-1"></div>
                                        <form method="POST" action="{{ route('users.destroy', $user->id) }}" class="block"
                                              onsubmit="return confirm('{{ addslashes(__('common.confirm_delete_user')) }}')" novalidate>
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                {{ __('common.delete') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table-empty-state :colspan="8" :message="__('common.no_users_found')"
                        :cta-href="route('users.create')" :cta-label="__('common.add_user')" />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-per-page-footer :paginator="$users" :perPage="$perPage" id="users-pagination" />

    <template id="users-skeleton-source">
        <x-skeleton-rows :rows="5" :cols="8" />
    </template>
</div>
@endsection
