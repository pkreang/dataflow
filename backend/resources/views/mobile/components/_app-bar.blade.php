@php
    $appName = config('app.name');
    $userInitials = strtoupper(
        mb_substr(session('user.first_name') ?? '', 0, 1)
        . mb_substr(session('user.last_name') ?? '', 0, 1)
    ) ?: 'U';
    $notifCount = 0;
    if ($userId = session('user.id')) {
        $u = \App\Models\User::find($userId);
        $notifCount = $u ? $u->unreadNotifications()->count() : 0;
    }
@endphp
<header class="sticky top-0 z-20 px-4 pt-4 pb-2">
    <div class="flex items-center gap-3">
        <a href="{{ route('mobile.home') }}" class="flex items-center gap-2 min-w-0">
            <div class="w-11 h-11 rounded-xl bg-white shadow-md flex items-center justify-center shrink-0 ring-1 ring-white/60">
                <span class="font-extrabold text-xl tracking-tight" style="color: var(--mob-navy)">
                    {{ mb_substr($appName, 0, 1) }}
                </span>
            </div>
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight truncate" style="color: var(--mob-navy)">{{ $appName }}</p>
                <p class="text-[10px] leading-tight truncate" style="color: var(--mob-muted)">{{ __('common.app_tagline') }}</p>
            </div>
        </a>

        <div class="flex-1"></div>

        <a href="{{ route('notifications.index') }}"
           class="relative w-10 h-10 rounded-xl bg-white/80 backdrop-blur shadow-sm ring-1 ring-white/60 flex items-center justify-center hover:bg-white"
           style="color: var(--mob-navy)">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14V11a6 6 0 00-12 0v3a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/>
            </svg>
            @if($notifCount > 0)
                <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-white">
                    {{ $notifCount > 99 ? '99+' : $notifCount }}
                </span>
            @endif
        </a>

        <a href="{{ route('mobile.me') }}"
           class="w-10 h-10 rounded-xl text-white shadow-sm flex items-center justify-center text-sm font-bold"
           style="background: linear-gradient(135deg, var(--mob-blue), var(--mob-navy))">
            {{ $userInitials }}
        </a>
    </div>
</header>
