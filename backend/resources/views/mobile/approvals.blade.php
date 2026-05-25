@extends('layouts.mobile')

@section('title', __('common.approvals'))

@section('content')

@php $count = is_iterable($instances ?? null) ? count($instances) : 0; @endphp

<section class="mb-4 mt-1 px-1">
    <h1 class="text-2xl font-bold leading-tight" style="color: var(--mob-navy)">{{ __('common.pending_approvals_heading') }}</h1>
    <p class="text-sm mt-1" style="color: var(--mob-muted)">{{ __('common.tap_to_review') }}</p>
</section>

<div class="mob-glass flex items-center gap-3 mb-4">
    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white" style="background: var(--mob-orange)">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="flex-1">
        <p class="text-xs" style="color: var(--mob-muted)">{{ __('common.pending_approvals') }}</p>
        <p class="text-2xl font-bold leading-tight" style="color: var(--mob-navy)">{{ $count }}</p>
    </div>
</div>

@if (session('success'))
    <div class="rounded-2xl px-3 py-2 text-sm mb-3" style="background: rgba(18,131,68,.12); color: var(--mob-green)">{{ session('success') }}</div>
@endif

@if(empty($instances) || $instances->isEmpty())
    <div class="mob-glass py-10 flex flex-col items-center gap-2" style="color: var(--mob-muted)">
        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-medium">{{ __('common.no_pending_approvals') }}</p>
    </div>
@else
    <div class="space-y-3">
        @foreach($instances as $instance)
            @php
                $current = $instance->steps->firstWhere('step_no', $instance->current_step_no);
                $totalSteps = $instance->steps->count();
                $docTypeLabel = \App\Models\DocumentType::allActive()->firstWhere('code', $instance->document_type)?->label()
                    ?? str_replace('_', ' ', $instance->document_type);
                $stepRequiresSig = (bool) ($current?->require_signature ?? false);
            @endphp
            <div class="mob-glass overflow-hidden p-0">
                <a href="#approval-{{ $instance->id }}-form" class="mob-list-card border-0" style="background: transparent">
                    <div class="mob-icon-square" style="background: rgba(15,110,216,.12); color: var(--mob-blue)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-mono text-xs font-bold truncate" style="color: var(--mob-navy)">{{ $instance->reference_no ?: '#'.$instance->id }}</p>
                            <span class="mob-badge tone-orange ml-auto">{{ __('common.pending') }}</span>
                        </div>
                        <p class="text-sm font-semibold truncate" style="color: var(--mob-navy)">{{ $docTypeLabel }}</p>
                        <p class="text-xs mt-0.5" style="color: var(--mob-muted)">
                            {{ optional($instance->requester)->full_name ?? '—' }} · {{ $instance->created_at?->format('d/m/Y H:i') }}
                            <span class="font-semibold ml-1" style="color: var(--mob-blue)">{{ __('common.step_short') }} {{ $instance->current_step_no }}/{{ $totalSteps }}</span>
                        </p>
                    </div>
                </a>

                <form id="approval-{{ $instance->id }}-form" method="POST" action="{{ route('approvals.act', $instance) }}"
                      class="px-3 pb-3 pt-1 border-t border-white/40 space-y-2" novalidate>
                    @csrf

                    @php
                        $priorApprovedSteps = $instance->steps
                            ->where('step_no', '<', $instance->current_step_no)
                            ->where('action', 'approved')
                            ->sortBy('step_no');
                    @endphp
                    @if($priorApprovedSteps->isNotEmpty())
                        <details class="rounded-lg border border-emerald-200 bg-emerald-50">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold flex items-center gap-2" style="color: var(--mob-green)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ __('common.prior_approvals') }} ({{ $priorApprovedSteps->count() }})
                            </summary>
                            <div class="px-3 pb-2 space-y-2">
                                @foreach($priorApprovedSteps as $prior)
                                    <div class="rounded-md bg-white border border-emerald-100 p-2">
                                        <p class="text-[11px] font-semibold mb-1" style="color: var(--mob-green)">
                                            ✓ {{ $prior->step_no }}. {{ $prior->stage_name }}
                                        </p>
                                        @foreach($prior->approved_by ?? [] as $entry)
                                            <div class="flex items-center gap-2 mt-1">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-[11px]" style="color: var(--mob-navy)">{{ $entry['name'] ?? '—' }}</p>
                                                    @if(! empty($entry['at']))
                                                        <p class="text-[10px]" style="color: var(--mob-muted)">{{ \Illuminate\Support\Carbon::parse($entry['at'])->format('d/m/Y H:i') }}</p>
                                                    @endif
                                                </div>
                                                @if(! empty($entry['signature']))
                                                    <img src="{{ $entry['signature'] }}" class="h-8 max-w-[60px] object-contain bg-white rounded border">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <textarea name="comment" rows="1" placeholder="{{ __('common.approval_comment_placeholder') }}"
                              class="form-input text-xs resize-y w-full"></textarea>
                    @if($stepRequiresSig)
                        <div>
                            <p class="text-[10px] font-medium mb-1" style="color: var(--mob-muted)">
                                {{ __('common.approval_signature_required_label') }} <span class="text-red-500">*</span>
                            </p>
                            <x-signature-pad name="signature_image" :saved-data-url="$mySignatureDataUrl ?? null" :required="true" />
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        <button type="submit" name="action" value="rejected"
                                class="rounded-xl text-white font-semibold text-xs py-2.5" style="background: #dc2626">
                            ✕ {{ __('common.reject') }}
                        </button>
                        <button type="submit" name="action" value="approved"
                                class="rounded-xl text-white font-semibold text-xs py-2.5" style="background: var(--mob-green)">
                            ✓ {{ __('common.approve') }}
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endif
@endsection
