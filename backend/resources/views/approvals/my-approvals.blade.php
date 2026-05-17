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

    <div class="space-y-4">
        @forelse($instances as $instance)
            @php
                $current = $instance->steps->firstWhere('step_no', $instance->current_step_no);
                $payload = $instance->payload ?? [];
                $daysPending = $instance->created_at ? (int) $instance->created_at->diffInDays(now()) : 0;
                $borderColor = match (true) {
                    $daysPending >= 3 => 'border-l-red-500',
                    $daysPending >= 1 => 'border-l-amber-400',
                    default => 'border-l-blue-400',
                };
                $docTypeModel = \App\Models\DocumentType::resolveByCode($instance->document_type);
                $docTypeLabel = $docTypeModel?->label() ?? str_replace('_', ' ', $instance->document_type);
            @endphp
            <div class="card border-l-4 {{ $borderColor }}">
                {{-- Header --}}
                <div class="p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-slate-100 font-mono break-all">
                                    {{ $instance->reference_no ?: ('#' . $instance->id) }}
                                </h3>
                                <span class="badge-blue inline-flex items-center gap-1">
                                    @if ($docTypeModel?->icon)
                                        <x-nav-icon :name="$docTypeModel->icon" class="w-3.5 h-3.5" />
                                    @endif
                                    <span>{{ $docTypeLabel }}</span>
                                </span>
                                @if($daysPending >= 3)
                                    <span class="badge-red text-[11px]">{{ $daysPending }} {{ __('common.days_pending') }}</span>
                                @elseif($daysPending >= 1)
                                    <span class="badge-yellow text-[11px]">{{ $daysPending }} {{ __('common.days_pending') }}</span>
                                @endif
                            </div>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                                {{ __('common.requester') }}: <span class="font-medium text-slate-700 dark:text-slate-300">{{ optional($instance->requester)->full_name ?? '—' }}</span>
                                · {{ $instance->created_at?->format('d M Y H:i') }}
                            </p>
                        </div>
                        @if($instance->formSubmission)
                            <a href="{{ route('forms.submission.show', $instance->formSubmission) }}"
                               class="btn-secondary text-xs shrink-0 self-start sm:self-auto">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span class="hidden sm:inline">{{ __('common.view_full_detail') }}</span>
                                <span class="sm:hidden">{{ __('common.detail') }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- Workflow steps --}}
                    @if($instance->steps->count())
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            @foreach($instance->steps as $step)
                                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs
                                    {{ $step->step_no == $instance->current_step_no ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-semibold ring-1 ring-blue-300 dark:ring-blue-700' :
                                       ($step->action === 'approved' ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' :
                                       ($step->action === 'rejected' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' :
                                        'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400')) }}">
                                    <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold
                                        {{ $step->action === 'approved' ? 'bg-green-500 text-white' :
                                           ($step->action === 'rejected' ? 'bg-red-500 text-white' :
                                           ($step->step_no == $instance->current_step_no ? 'bg-blue-500 text-white' :
                                            'bg-slate-300 dark:bg-slate-600 text-slate-600 dark:text-slate-300')) }}">
                                        {{ $step->step_no }}
                                    </span>
                                    {{ $step->stage_name }}
                                    @if(($step->min_approvals ?? 1) > 1)
                                        <span class="opacity-75">({{ count($step->approved_by ?? []) }}/{{ $step->min_approvals }})</span>
                                    @endif
                                </div>
                                @if(!$loop->last)
                                    <svg class="w-3 h-3 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Action form — mobile-first: stacked, big touch buttons --}}
                @php $stepRequiresSig = (bool) ($current?->require_signature ?? false); @endphp
                <form method="POST" action="{{ route('approvals.act', $instance) }}"
                      class="px-4 sm:px-5 pb-4 sm:pb-5 pt-3 border-t border-slate-100 dark:border-slate-700 space-y-3" novalidate>
                    @csrf

                    @php
                        $priorApprovedSteps = $instance->steps
                            ->where('step_no', '<', $instance->current_step_no)
                            ->where('action', 'approved')
                            ->sortBy('step_no');
                    @endphp
                    @if($priorApprovedSteps->isNotEmpty())
                        <details class="rounded-lg border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-900/10">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ __('common.prior_approvals') }} ({{ $priorApprovedSteps->count() }})
                            </summary>
                            <div class="px-3 pb-3 pt-1 space-y-2">
                                @foreach($priorApprovedSteps as $prior)
                                    <div class="rounded-md bg-white dark:bg-slate-900/50 border border-emerald-200 dark:border-emerald-900/40 p-2">
                                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-1">
                                            ✓ {{ __('common.step_short') }} {{ $prior->step_no }}: {{ $prior->stage_name }}
                                        </p>
                                        @foreach($prior->approved_by ?? [] as $entry)
                                            <div class="flex items-center gap-3 mt-1">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs text-slate-700 dark:text-slate-300 truncate">{{ $entry['name'] ?? '—' }}</p>
                                                    @if(! empty($entry['comment']))
                                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2">{{ $entry['comment'] }}</p>
                                                    @endif
                                                    @if(! empty($entry['at']))
                                                        <p class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($entry['at'])->format('d/m/Y H:i') }}</p>
                                                    @endif
                                                </div>
                                                @if(! empty($entry['signature']))
                                                    <img src="{{ $entry['signature'] }}" alt="signature"
                                                         class="h-10 max-w-[80px] object-contain bg-white rounded border border-slate-200">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <textarea name="comment" rows="2" placeholder="{{ __('common.approval_comment_placeholder') }}"
                              class="form-input text-sm resize-y w-full"></textarea>
                    @if($stepRequiresSig)
                        <div>
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                {{ __('common.approval_signature_required_label') }}
                                <span class="text-red-500">*</span>
                            </p>
                            <x-signature-pad name="signature_image" :saved-data-url="$mySignatureDataUrl ?? null" :required="true" />
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end sm:gap-2 sm:items-center">
                        <button type="submit" name="action" value="rejected"
                                class="btn-danger justify-center py-3 sm:py-2 sm:order-1 w-full sm:w-auto">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            {{ __('common.reject') }}
                        </button>
                        <button type="submit" name="action" value="approved"
                                class="btn-primary justify-center py-3 sm:py-2 sm:order-2 w-full sm:w-auto">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('common.approve') }}
                        </button>
                    </div>
                </form>
            </div>
        @empty
            <div class="card p-10 flex flex-col items-center gap-3 text-slate-400 dark:text-slate-500">
                <svg class="w-12 h-12 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-medium">{{ __('common.no_pending_approvals') }}</p>
            </div>
        @endforelse
    </div>
@endsection
