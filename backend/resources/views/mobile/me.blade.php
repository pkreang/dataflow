@extends('layouts.mobile')

@section('title', __('common.profile'))

@section('content')
@php
    $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'User';
    $initials = strtoupper(mb_substr($user->first_name ?? '', 0, 1).mb_substr($user->last_name ?? '', 0, 1)) ?: 'U';
@endphp

{{-- Profile header --}}
<div class="mob-glass flex flex-col items-center py-6 mb-4">
    <div class="w-20 h-20 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-lg mb-3"
         style="background: linear-gradient(135deg, var(--mob-blue), var(--mob-navy))">
        {{ $initials }}
    </div>
    <h2 class="text-lg font-bold" style="color: var(--mob-navy)">{{ $fullName }}</h2>
    <p class="text-xs mt-0.5" style="color: var(--mob-muted)">{{ $user->email ?? '' }}</p>
    @if($user?->jobPosition)
        <p class="text-xs mt-1" style="color: var(--mob-muted)">
            {{ $user->jobPosition->name }}{{ $user->orgUnit ? ' · '.$user->orgUnit->name : '' }}
        </p>
    @endif
</div>

{{-- Settings rows --}}
<div class="space-y-2">
    @php
        $rows = [
            ['icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'href' => route('profile.edit'), 'label' => __('common.my_profile'), 'tone' => 'blue'],
            ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'href' => route('profile.password'), 'label' => __('common.change_password') ?? 'Change password', 'tone' => 'orange'],
            ['icon' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14V11a6 6 0 00-12 0v3a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', 'href' => route('notifications.index'), 'label' => __('common.notifications'), 'tone' => 'purple'],
        ];
    @endphp
    @foreach($rows as $row)
        <a href="{{ $row['href'] }}" class="mob-list-card">
            <div class="mob-icon-square text-white" style="background: var(--mob-{{ $row['tone'] }})">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['icon'] }}"/>
                </svg>
            </div>
            <span class="flex-1 text-sm font-medium" style="color: var(--mob-navy)">{{ $row['label'] }}</span>
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" style="color: var(--mob-muted)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    @endforeach

    <form method="POST" action="{{ route('logout') }}" class="pt-2">
        @csrf
        <button type="submit"
                class="w-full flex items-center justify-center gap-2 p-3 rounded-2xl text-white font-semibold text-sm"
                style="background: #dc2626">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            {{ __('common.sign_out') }}
        </button>
    </form>
</div>
@endsection
