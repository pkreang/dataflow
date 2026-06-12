@extends('layouts.app')

@section('title', __('common.users'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.users')],
    ]" />
@endsection

@section('content')
@php $viewerIsSuperAdmin = (bool) session('user.is_super_admin', false); @endphp
<div x-data="userIndex({{ json_encode(request('search', '')) }})">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.all_users') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $totalUsers }} {{ Str::plural('user', $totalUsers) }} total</p>
        </div>
        @if($viewerIsSuperAdmin)
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
        @endif
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
                    <th class="table-header px-6 py-3 text-left">{{ __('common.last_active') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('users.phone') }}</th>
                    <th class="table-header px-6 py-3 text-left">{{ __('common.status') }}</th>
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
                            @php
                                $today = now()->toDateString();
                                $activeShift = $user->shiftSchedules
                                    ->first(fn ($s) => $s->effective_from->toDateString() <= $today
                                        && ($s->effective_to === null || $s->effective_to->toDateString() >= $today))
                                    ?->shift;
                            @endphp
                            @if ($activeShift)
                                <span class="block text-xs text-slate-400">{{ __('common.shift') }}: {{ $activeShift->code }} ({{ substr($activeShift->start_time, 0, 5) }}–{{ substr($activeShift->end_time, 0, 5) }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @foreach ($user->roles as $role)
                                <span class="badge-blue mr-1">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $lastActiveText }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $user->phone ?? '-' }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @if ($user->is_active ?? true)
                                <span class="badge-green">{{ __('common.active') }}</span>
                            @else
                                <span class="badge-red">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-right">
                            @php
                                $rowActions = [];
                                if ($viewerIsSuperAdmin) {
                                    $rowActions[] = ['label' => __('common.edit'), 'href' => route('users.edit', $user->id), 'icon' => 'edit'];
                                    $rowActions[] = [
                                        'label' => ($user->is_active ?? true) ? __('common.disable') : __('common.enable'),
                                        'method' => 'PUT',
                                        'action' => route('users.update', $user->id),
                                        'icon' => 'toggle',
                                        'hidden' => ['toggle_active' => '1'],
                                    ];
                                    if (!$isSuperAdmin) {
                                        $rowActions[] = [
                                            'label' => __('common.delete'),
                                            'method' => 'DELETE',
                                            'action' => route('users.destroy', $user->id),
                                            'icon' => 'delete',
                                            'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
                                            'confirm' => __('common.confirm_delete_user'),
                                        ];
                                    }
                                }
                            @endphp
                            @if(!empty($rowActions))
                                <x-row-actions :items="$rowActions" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table-empty-state :colspan="8" :message="__('common.no_users_found')"
                        :cta-href="$viewerIsSuperAdmin ? route('users.create') : null"
                        :cta-label="$viewerIsSuperAdmin ? __('common.add_user') : null" />
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
