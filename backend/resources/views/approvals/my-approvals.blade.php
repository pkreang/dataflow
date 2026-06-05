@extends('layouts.app')

@section('title', __('common.my_approvals'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.my_approvals')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.my_approvals') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.my_approvals_desc') }}</p>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if ($errors->has('approval'))
        <div class="alert-error mb-4">{{ $errors->first('approval') }}</div>
    @endif

    @php
        $hasPending = ! $grouped->isEmpty();
        $hasActed   = isset($actedGrouped) && ! $actedGrouped->isEmpty();
    @endphp

    @if (! $hasPending && ! $hasActed)
        <div class="card p-10 flex flex-col items-center gap-3 text-slate-400 dark:text-slate-500">
            <svg class="w-12 h-12 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium">{{ __('common.no_pending_approvals') }}</p>
        </div>
    @else
        <div class="space-y-10">
            @if ($hasPending)
                @include('approvals._group-section', [
                    'grouped' => $grouped,
                    'mode' => 'pending',
                    'title' => __('common.awaiting_my_approval'),
                ])
            @endif
            @if ($hasActed)
                @include('approvals._group-section', [
                    'grouped' => $actedGrouped,
                    'mode' => 'history',
                    'title' => __('common.approvals_acted_history'),
                ])
            @endif
        </div>
    @endif
@endsection
