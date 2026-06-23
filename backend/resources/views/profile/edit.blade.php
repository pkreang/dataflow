@extends('layouts.app')

@section('title', __('common.my_profile'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.my_profile')],
    ]" />
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

    @if(session('success'))
    <div class="alert-success">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('info'))
    <div class="alert-info">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('info') }}
    </div>
    @endif

    @if(session('error'))
    <div class="alert-error">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/>
        </svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Last login / security card --}}
    <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ __('common.last_login') }}</h3>
            <a href="{{ route('profile.login-history') }}" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">{{ __('common.login_history_view_all') }} &rarr;</a>
        </div>
        @if($lastPriorLogin)
            <div class="text-sm text-slate-700 dark:text-slate-300">
                {{ $lastPriorLogin->created_at->diffForHumans() }}
                &middot; {{ $lastPriorLogin->ip_address ?: '—' }}
                &middot; {{ \App\Services\Auth\LoginHistoryRecorder::summarizeUserAgent($lastPriorLogin->user_agent) }}
            </div>
        @else
            <div class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.login_never') }}</div>
        @endif

        @if($recentFailedLogins > 0)
            <div class="alert-warning mt-3 text-sm">
                {{ __('common.login_failed_alert', ['count' => $recentFailedLogins]) }}
            </div>
        @endif
    </div>

    {{-- Quick access card --}}
    <div class="card p-4">
        <h3 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">{{ __('common.quick_access') }}</h3>
        <div class="grid grid-cols-3 gap-2">
            <div class="flex flex-col items-center justify-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                <span class="text-2xl font-bold text-amber-500">{{ $quickStats['draft'] }}</span>
                <span class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ __('common.my_drafts') }}</span>
            </div>
            <div class="flex flex-col items-center justify-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                <span class="text-2xl font-bold text-blue-500">{{ $quickStats['submitted'] }}</span>
                <span class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ __('common.my_submissions_count') }}</span>
            </div>
            <a href="{{ route('approvals.my') }}" class="flex flex-col items-center justify-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">
                <span class="text-2xl font-bold text-emerald-500">{{ $quickStats['pending_approvals'] }}</span>
                <span class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ __('common.my_pending_approvals') }}</span>
            </a>
        </div>
    </div>

    {{-- Profile Card --}}
    <div class="card p-6">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')

            {{-- Avatar --}}
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100 dark:border-slate-700"
                 x-data="{ preview: '{{ $user->avatar }}', remove: false }">
                @php
                    $avatarColors = ['#3B82F6', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444'];
                    $colorIndex = abs(crc32($user->full_name)) % 5;
                    $initials = strtoupper(mb_substr($user->first_name ?? '', 0, 1) . mb_substr($user->last_name ?? '', 0, 1)) ?: '??';
                @endphp
                <div class="flex-shrink-0">
                    <template x-if="preview && !remove">
                        <img :src="preview" alt="" class="w-20 h-20 rounded-full object-cover">
                    </template>
                    <template x-if="!preview || remove">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center text-xl font-bold text-white"
                             style="background: {{ $avatarColors[$colorIndex] }}">
                            {{ $initials }}
                        </div>
                    </template>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $user->full_name }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                    @foreach($user->roles as $role)
                        <span class="badge-blue mt-1 inline-block">{{ $role->display_name ?? $role->name }}</span>
                    @endforeach
                    <div class="mt-2 flex items-center gap-3">
                        <label class="text-sm text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                            <input type="file" name="avatar" accept="image/*" class="hidden"
                                   @change="
                                       remove = false;
                                       if ($event.target.files[0]) {
                                           const r = new FileReader();
                                           r.onload = e => preview = e.target.result;
                                           r.readAsDataURL($event.target.files[0]);
                                       }
                                   ">
                            {{ __('common.avatar_change') }}
                        </label>
                        <template x-if="preview && !remove">
                            <button type="button" @click="remove = true; preview = ''"
                                    class="text-sm text-red-500 hover:underline">
                                {{ __('common.avatar_remove') }}
                            </button>
                        </template>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ __('common.avatar_upload_hint') }}</p>
                    <input type="hidden" name="remove_avatar" :value="remove ? '1' : '0'">
                </div>
            </div>

            {{-- Signature --}}
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100 dark:border-slate-700"
                 x-data="{ preview: '{{ $user->signature_path }}', remove: false }">
                <div class="flex-shrink-0">
                    <template x-if="preview && !remove">
                        <img :src="preview" alt="" class="h-16 w-32 object-contain border border-slate-200 dark:border-slate-700 bg-white">
                    </template>
                    <template x-if="!preview || remove">
                        <div class="h-16 w-32 flex items-center justify-center text-xs text-slate-400 border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800">
                            {{ __('common.profile_signature_empty') }}
                        </div>
                    </template>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.profile_signature') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.profile_signature_help') }}</p>
                    <div class="mt-2 flex items-center gap-3">
                        <label class="text-sm text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">
                            <input type="file" name="signature" accept="image/png,image/jpeg" class="hidden"
                                   @change="
                                       remove = false;
                                       if ($event.target.files[0]) {
                                           const r = new FileReader();
                                           r.onload = e => preview = e.target.result;
                                           r.readAsDataURL($event.target.files[0]);
                                       }
                                   ">
                            {{ __('common.profile_signature_upload') }}
                        </label>
                        <template x-if="preview && !remove">
                            <button type="button" @click="remove = true; preview = ''"
                                    class="text-sm text-red-500 hover:underline">
                                {{ __('common.profile_signature_remove') }}
                            </button>
                        </template>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ __('common.profile_signature_hint') }}</p>
                    <input type="hidden" name="remove_signature" :value="remove ? '1' : '0'">
                </div>
            </div>

            <div class="space-y-4">

                @php $lockedClass = 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 cursor-not-allowed'; @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="form-label">
                            {{ __('common.first_name') }} @unless($isSsoUser)<span class="text-red-500">*</span>@endunless
                        </label>
                        <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}"
                               @if($isSsoUser) readonly @endif
                               class="form-input @if($isSsoUser) {{ $lockedClass }} @endif">
                        @if($isSsoUser)
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.field_locked_sso_only') }}</p>
                        @endif
                        @error('first_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="last_name" class="form-label">
                            {{ __('common.last_name') }} @unless($isSsoUser)<span class="text-red-500">*</span>@endunless
                        </label>
                        <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}"
                               @if($isSsoUser) readonly @endif
                               class="form-input @if($isSsoUser) {{ $lockedClass }} @endif">
                        @if($isSsoUser)
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.field_locked_sso_only') }}</p>
                        @endif
                        @error('last_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="form-label">{{ __('common.email') }} @if($canEditEmail)<span class="text-red-500">*</span>@endif</label>
                    @if($canEditEmail)
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required maxlength="255"
                               class="form-input @error('email') form-input-error @enderror">
                        @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    @else
                        <input type="email" id="email" value="{{ $user->email }}" readonly
                               class="form-input bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 cursor-not-allowed">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('users.email_readonly_hint') }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="form-label">{{ __('common.phone') }}</label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" maxlength="50"
                               class="form-input" placeholder="0x-xxxx-xxxx">
                        @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="locale" class="form-label">{{ __('common.language') }}</label>
                        <select name="locale" id="locale" class="form-input">
                            @foreach($availableLocales as $code => $label)
                                <option value="{{ $code }}" @selected(old('locale', $user->locale ?? app()->getLocale()) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="theme" class="form-label">{{ __('common.theme') }}</label>
                        <select name="theme" id="theme" class="form-input">
                            <option value="system" @selected(old('theme', $user->theme ?? 'system') === 'system')>{{ __('common.theme_system') }}</option>
                            <option value="light" @selected(old('theme', $user->theme) === 'light')>{{ __('common.theme_light') }}</option>
                            <option value="dark" @selected(old('theme', $user->theme) === 'dark')>{{ __('common.theme_dark') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="density" class="form-label">{{ __('common.density') }}</label>
                        <select name="density" id="density" class="form-input">
                            <option value="comfortable" @selected(old('density', $user->density ?? 'comfortable') === 'comfortable')>{{ __('common.density_comfortable') }}</option>
                            <option value="compact" @selected(old('density', $user->density) === 'compact')>{{ __('common.density_compact') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">{{ __('common.org_unit') }}</label>
                        <input type="text" value="{{ $user->orgUnit?->name ?: '—' }}" readonly
                               class="form-input {{ $lockedClass }}">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.field_locked_admin_only') }}</p>
                    </div>
                    <div>
                        <label class="form-label">{{ __('common.position') }}</label>
                        @php
                            $currentPosition = $positions->firstWhere('id', $user->position_id);
                            $positionDisplay = $currentPosition ? $currentPosition->name.' ('.$currentPosition->code.')' : '—';
                        @endphp
                        <input type="text" value="{{ $positionDisplay }}" readonly
                               class="form-input {{ $lockedClass }}">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.field_locked_admin_only') }}</p>
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755z"/></svg>
                            LINE Official Account
                        </span>
                    </label>
                    @if($user->line_user_id)
                        <div class="flex items-center gap-3 mt-1">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                {{ __('notifications.line_linked') }}
                            </span>
                        </div>
                    @else
                        <a href="{{ route('auth.line.redirect') }}" class="btn-secondary inline-flex items-center gap-2 mt-1">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755z"/></svg>
                            {{ __('notifications.line_link_account') }}
                        </a>
                    @endif
                </div>

            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
                <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
            </div>
        </form>

        @if($user->line_user_id)
            {{-- Unlink LINE — MUST be outside the main profile form (nested forms are invalid HTML) --}}
            <form method="POST" action="{{ route('auth.line.unlink') }}" class="mt-2 px-4">
                @csrf
                <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">
                    {{ __('notifications.line_unlink') }}
                </button>
            </form>
        @endif
    </div>

    {{-- Connected account / SSO info --}}
    <div class="card p-4">
        <h3 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">{{ __('common.account_connection') }}</h3>
        @if(empty($user->auth_provider))
            <p class="text-sm text-slate-700 dark:text-slate-300">{{ __('common.auth_local_managed') }}</p>
            @if($user->password_changed_at)
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('common.password_last_changed') }}: {{ $user->password_changed_at->diffForHumans() }}
                </p>
            @endif
        @elseif($user->auth_provider === 'entra')
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 shrink-0 mt-0.5" viewBox="0 0 23 23" fill="none">
                    <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
                    <rect x="12" y="1" width="10" height="10" fill="#7FBA00"/>
                    <rect x="1" y="12" width="10" height="10" fill="#00A4EF"/>
                    <rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Microsoft Entra (Azure AD)</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $user->external_id ?? '—' }}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ __('common.password_managed_by_entra') }}</p>
                </div>
            </div>
        @elseif($user->auth_provider === 'ldap')
            <div>
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">LDAP / Active Directory</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all mt-1">{{ $user->ldap_dn ?? '—' }}</p>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ __('common.password_managed_by_ldap') }}</p>
            </div>
        @else
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $user->auth_provider }}</p>
        @endif
        @if($user->email_verified_at)
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                ✓ {{ __('common.email_verified') }} · {{ $user->email_verified_at->format('d M Y') }}
            </p>
        @endif
    </div>

    {{-- Home dashboard preference --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.home_dashboard') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">{{ __('common.home_dashboard_desc') }}</p>
        <form method="POST" action="{{ route('profile.home-dashboard.update') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('PATCH')
            <div class="flex-1 min-w-[240px]">
                <label for="home_dashboard_id" class="form-label">{{ __('common.dashboard') }}</label>
                <select name="home_dashboard_id" id="home_dashboard_id" class="form-input mt-1">
                    <option value="">{{ __('common.use_system_default') }}</option>
                    @foreach($availableHomeDashboards as $dashboardOption)
                        <option value="{{ $dashboardOption->id }}"
                            @selected(old('home_dashboard_id', $user->home_dashboard_id) == $dashboardOption->id)>
                            {{ $dashboardOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('home_dashboard_id')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        </form>
    </div>

    {{-- Notification preferences --}}
    <div class="card p-6">
        <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.notification_prefs') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">{{ __('common.notification_prefs_desc') }}</p>
        <form method="POST" action="{{ route('profile.notifications.update') }}">
            @csrf
            @method('PUT')
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 dark:text-slate-400">
                            <th class="py-2 pr-4"></th>
                            @foreach($notificationChannels as $channel)
                                <th class="py-2 pr-4 text-center">{{ __('common.channel_'.$channel) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notificationEvents as $event)
                            <tr class="border-t border-slate-100 dark:border-slate-700">
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ __('common.event_'.$event) }}</td>
                                @foreach($notificationChannels as $channel)
                                    <td class="py-3 pr-4 text-center">
                                        <input type="checkbox"
                                               name="notifications[{{ $event }}][{{ $channel }}]"
                                               value="1"
                                               @checked($notificationPreferences[$event][$channel] ?? true)>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
            </div>
        </form>
    </div>

    @if (empty($canChangePasswordInApp))
    <div class="alert-warning rounded-xl p-6">
        <h3 class="text-sm font-semibold mb-2">{{ __('auth.password_managed_by_org') }}</h3>
        <p class="text-sm mb-3">{{ __('auth.password_use_org_portal') }}</p>
        @if (! empty($authPasswordHelpUrl))
            <a href="{{ $authPasswordHelpUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 text-sm font-medium underline hover:no-underline">
                {{ __('auth.open_password_help_link') }}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        @endif
    </div>
    @endif
</div>
@endsection
