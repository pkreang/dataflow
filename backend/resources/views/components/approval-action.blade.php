@props(['instance'])

@php
    // Shared approval action card — used on every document detail page
    // (forms.submission.show + legacy repair/spare/purchase show). The caller is
    // responsible for gating with @if($canAct) (status pending + approval.approve
    // permission + canUserActOnStep) — this component only renders the form.
    //
    // Rendered expanded (never display:none) so the signature pad's canvas sizes
    // correctly. self-fetches the actor's saved signature (same as ApprovalController).
    $current = $instance->steps->firstWhere('step_no', $instance->current_step_no);
    $stepRequiresSig = (bool) ($current?->require_signature ?? false);
    $priorApprovedSteps = $instance->steps
        ->where('step_no', '<', $instance->current_step_no)
        ->where('action', 'approved')
        ->sortBy('step_no');
    $actorId = (int) (session('user.id') ?? 0);
    $mySignatureDataUrl = $actorId
        ? \App\Models\User::query()->whereKey($actorId)->value('signature_path')
        : null;
@endphp

<div class="card p-4 sm:p-6 mt-6" x-data="{
        sendBackOpen: false,
        sigError: false,
        guardSignature($event) {
            // Block submit (and the wasted server round-trip) when this stage
            // requires a signature but none was captured — instant feedback
            // instead of a silent bounce-back.
            const sig = $event.target.querySelector('[data-required-signature]');
            if (sig && !sig.value) {
                $event.preventDefault();
                this.sigError = true;
                sig.closest('.signature-pad')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            this.sigError = false;
        },
     }">
    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ __('common.approval_actions_title') }}</h3>

    @if($stepRequiresSig)
        <div x-show="sigError" x-cloak class="alert-error mb-3 text-sm">
            {{ __('common.approval_signature_required_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('approvals.act', $instance) }}" class="space-y-3" novalidate
          @submit="guardSignature($event)">
        @csrf

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
                <x-signature-pad name="signature_image" :saved-data-url="$mySignatureDataUrl" :required="true" />
            </div>
        @endif
        @if($instance->formSubmission)
            <button type="button" @click="sendBackOpen = true"
                    class="btn-secondary justify-center py-3 sm:py-2 w-full text-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l5-5M3 10l5 5"/></svg>
                {{ __('common.action_send_back') }}
            </button>
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

    @if($instance->formSubmission)
        {{-- Send-back dialog — its own <form>, kept outside the approvals.act form --}}
        <div x-show="sendBackOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="sendBackOpen = false">
            <div class="fixed inset-0 bg-black/50" @click="sendBackOpen = false"></div>
            <div class="relative card p-5 w-full max-w-md">
                <h4 class="text-base font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.action_send_back') }}</h4>
                <form method="POST" action="{{ route('forms.submission.send-back', $instance->formSubmission) }}" class="space-y-3">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="radio" name="destination" value="requester" checked class="mt-0.5">
                        <span>{{ __('common.send_back_to_requester') }}</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm {{ $instance->current_step_no <= 1 ? 'opacity-40' : 'text-slate-700 dark:text-slate-300' }}">
                        <input type="radio" name="destination" value="previous_step" class="mt-0.5"
                               {{ $instance->current_step_no <= 1 ? 'disabled' : '' }}>
                        <span>{{ __('common.send_back_to_previous_step') }}</span>
                    </label>
                    <textarea name="comment" rows="3" required
                              placeholder="{{ __('common.send_back_reason') }}"
                              class="form-input text-sm resize-y w-full"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="sendBackOpen = false" class="btn-secondary text-sm">{{ __('common.cancel') }}</button>
                        <button type="submit" class="btn-primary text-sm">{{ __('common.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
