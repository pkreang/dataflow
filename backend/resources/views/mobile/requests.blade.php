@extends('layouts.mobile')

@section('title', __('common.my_requests'))

@section('content')
<section class="mb-4 mt-1 px-1">
    <h1 class="text-2xl font-bold leading-tight" style="color: var(--mob-navy)">{{ __('common.my_requests') }}</h1>
    <p class="text-sm mt-1" style="color: var(--mob-muted)">{{ __('common.requests_list_subtitle') }}</p>
</section>

@if($submissions->isEmpty())
    <div class="mob-glass py-10 flex flex-col items-center gap-2" style="color: var(--mob-muted)">
        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm font-medium">{{ __('common.no_requests_yet') }}</p>
    </div>
@else
    <div class="space-y-2">
        @foreach($submissions as $sub)
            @php
                $instance = $sub->instance;
                $status = $sub->status;
                $approvalStatus = $instance?->status;
                [$pillTone, $pillLabel] = match(true) {
                    $approvalStatus === 'approved' => ['green', __('common.approved')],
                    $approvalStatus === 'rejected' => ['red', __('common.rejected')],
                    $status === 'submitted' => ['orange', __('common.pending')],
                    default => ['gray', __('common.draft') ?? 'Draft'],
                };
                $totalSteps = $instance?->steps?->count() ?? 0;
                $currentStep = $instance?->current_step_no ?? 0;
            @endphp
            @php
                $canEvaluate = $approvalStatus === 'approved'
                    && $sub->parent_submission_id === null
                    && (bool) ($sub->form?->evaluation_enabled ?? false);
                $existingEval = $canEvaluate ? $sub->evaluations->first() : null;
            @endphp
            <div class="mob-list-card">
                <a href="{{ route('mobile.request.detail', $sub) }}" class="flex items-center gap-3 flex-1 min-w-0" style="text-decoration: none">
                    <div class="mob-icon-square" style="background: rgba(15,110,216,.12); color: var(--mob-blue)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-mono text-xs font-bold truncate" style="color: var(--mob-navy)">
                                {{ $sub->reference_no ?: '#'.$sub->id }}
                            </p>
                            <span class="mob-badge tone-{{ $pillTone }} ml-auto">{{ $pillLabel }}</span>
                        </div>
                        <p class="text-sm font-semibold truncate" style="color: var(--mob-navy)">{{ $sub->form?->name ?? '—' }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--mob-muted)">
                            {{ $sub->created_at?->format('d/m/Y H:i') }}
                            @if($totalSteps > 0)
                                · <span style="color: var(--mob-blue)" class="font-semibold">{{ __('common.step_short') }} {{ $currentStep }}/{{ $totalSteps }}</span>
                            @endif
                        </p>
                    </div>
                </a>
                @if($canEvaluate)
                    <a href="{{ $existingEval ? route('mobile.request.detail', $existingEval) : route('mobile.request.evaluate', $sub) }}"
                       class="mob-badge tone-{{ $existingEval ? 'gray' : 'orange' }} shrink-0 ml-2 text-[11px] font-semibold"
                       style="text-decoration: none">
                        {{ $existingEval ? __('common.view_evaluation') : __('common.action_evaluate') }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection
