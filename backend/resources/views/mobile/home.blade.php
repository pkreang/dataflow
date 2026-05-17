@extends('layouts.mobile')

@section('title', __('common.overview'))

@section('content')
@php
    $userName = trim((session('user.first_name') ?? '').' '.(session('user.last_name') ?? '')) ?: (session('user.name') ?? '');
@endphp

{{-- Greeting --}}
<section class="mb-4 mt-1 px-1">
    <h1 class="text-2xl font-bold leading-tight" style="color: var(--mob-navy)">
        {{ __('common.greeting_hello') }}, {{ $userName }}
    </h1>
    <p class="text-sm mt-1" style="color: var(--mob-muted)">{{ __('common.welcome_back') }}</p>
</section>

{{-- 4 KPI cards grid (2x2) --}}
<section class="grid grid-cols-2 gap-3 mb-4">
    <a href="{{ route('mobile.approvals') }}" class="mob-kpi">
        <div class="mob-kpi-icon tone-orange">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-xs mt-1" style="color: var(--mob-muted)">{{ __('common.pending_approvals') }}</p>
        <p class="text-2xl font-bold" style="color: var(--mob-navy)">{{ number_format($kpis['pending_approvals']) }}</p>
    </a>
    <div class="mob-kpi">
        <div class="mob-kpi-icon tone-green">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-xs mt-1" style="color: var(--mob-muted)">{{ __('common.approved') }}</p>
        <p class="text-2xl font-bold" style="color: var(--mob-navy)">{{ number_format($kpis['approved_count']) }}</p>
    </div>
    <a href="{{ route('mobile.forms') }}" class="mob-kpi">
        <div class="mob-kpi-icon tone-blue">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <p class="text-xs mt-1" style="color: var(--mob-muted)">{{ __('common.forms') }}</p>
        <p class="text-2xl font-bold" style="color: var(--mob-navy)">{{ number_format($kpis['forms_count']) }}</p>
    </a>
    <a href="{{ route('mobile.reports') }}" class="mob-kpi">
        <div class="mob-kpi-icon tone-purple">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </div>
        <p class="text-xs mt-1" style="color: var(--mob-muted)">{{ __('common.reports') }}</p>
        <p class="text-2xl font-bold" style="color: var(--mob-navy)">{{ number_format($kpis['reports_count']) }}</p>
    </a>
</section>

{{-- Pending approvals list --}}
<section class="mob-glass mb-4">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold" style="color: var(--mob-navy)">{{ __('common.pending_approvals_heading') }}</h2>
        <a href="{{ route('mobile.approvals') }}" class="text-xs font-semibold" style="color: var(--mob-blue)">{{ __('common.view_all') }} →</a>
    </div>

    @if ($pendingPreview->isEmpty())
        <p class="text-center text-sm py-6" style="color: var(--mob-muted)">{{ __('common.no_pending_items') }}</p>
    @else
        <div class="space-y-2">
            @foreach ($pendingPreview as $instance)
                @php
                    $sub = $instance->formSubmission;
                    $formName = $sub?->form?->name ?? '—';
                    $requester = $instance->requester;
                    $requesterName = $requester ? trim(($requester->first_name ?? '').' '.($requester->last_name ?? '')) : '—';
                    $totalSteps = $instance->steps->count();
                    $current = $instance->current_step_no ?? 1;
                @endphp
                <a href="{{ $sub ? route('mobile.request.detail', $sub) : '#' }}" class="mob-list-card">
                    <div class="mob-icon-square" style="background: rgba(15,110,216,.12); color: var(--mob-blue)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-mono mb-0.5" style="color: var(--mob-muted)">{{ $sub?->reference_no ?? '#'.$instance->id }}</p>
                        <p class="text-sm font-semibold truncate" style="color: var(--mob-navy)">{{ $formName }}</p>
                        <p class="text-xs truncate" style="color: var(--mob-muted)">{{ $requesterName }} · {{ $instance->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                    <span class="mob-badge tone-orange shrink-0">{{ $current }}/{{ $totalSteps }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>

{{-- Quick forms + latest approval --}}
<section class="space-y-3 mb-4">
    <div class="mob-glass">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold" style="color: var(--mob-navy)">{{ __('common.quick_forms_heading') }}</h3>
            <a href="{{ route('mobile.forms') }}" class="w-7 h-7 rounded-full flex items-center justify-center text-white" style="background: var(--mob-blue)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </a>
        </div>
        <div class="grid grid-cols-4 gap-2">
            @foreach ($quickForms as $form)
                <a href="{{ route('mobile.form.create', $form->form_key) }}" class="flex flex-col items-center gap-2 p-1 rounded-2xl hover:bg-white/40">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background: rgba(15,110,216,.12); color: var(--mob-blue)">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-[11px] text-center leading-tight font-medium line-clamp-2" style="color: var(--mob-navy)">{{ $form->name }}</p>
                </a>
            @endforeach
            <a href="{{ route('mobile.forms') }}" class="flex flex-col items-center gap-2 p-1 rounded-2xl hover:bg-white/40">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center" style="background: rgba(66,87,127,.10); color: var(--mob-muted)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01"/></svg>
                </div>
                <p class="text-[11px] text-center font-medium" style="color: var(--mob-muted)">{{ __('common.view_all') }}</p>
            </a>
        </div>
    </div>

    @if ($latestApproval && $latestApproval->formSubmission)
        @php
            $sub = $latestApproval->formSubmission;
            $req = $latestApproval->requester;
            $reqName = $req ? trim(($req->first_name ?? '').' '.($req->last_name ?? '')) : '—';
        @endphp
        <div class="mob-glass">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold" style="color: var(--mob-navy)">{{ __('common.latest_approval_heading') }}</h3>
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-white" style="background: var(--mob-green)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
            </div>
            <p class="text-xs font-mono mb-1" style="color: var(--mob-muted)">{{ $sub->reference_no ?? '#'.$latestApproval->id }}</p>
            <p class="text-sm font-semibold mb-1" style="color: var(--mob-navy)">{{ $sub->form?->name ?? '—' }}</p>
            <p class="text-xs leading-relaxed" style="color: var(--mob-muted)">
                {{ __('common.requester') }}: {{ $reqName }}<br>
                {{ $latestApproval->updated_at?->format('d/m/Y H:i') }}
            </p>
            <a href="{{ route('mobile.request.detail', $sub) }}"
               class="mt-3 w-full rounded-xl text-white font-semibold py-2.5 flex items-center justify-center gap-2 text-sm"
               style="background: var(--mob-blue)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                {{ __('common.view') }}
            </a>
        </div>
    @endif
</section>
@endsection
