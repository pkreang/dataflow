@props(['instance', 'submittedAt' => null])

@php
    // Build stepper data: submit → workflow stages → final outcome.
    // Shared by /forms/submissions/* (detail) and /approvals/my (list) so both
    // render an identical horizontal stepper. `submittedAt` is passed in by the
    // caller (not derived from a relation here) to avoid N+1 in the list view.
    $isApproved = $instance->status === 'approved';
    $isRejected = $instance->status === 'rejected';
    $isPending  = $instance->status === 'pending';

    $submitTs = $submittedAt ?? $instance->created_at;

    // step 0: submitted
    $stepperSteps = [[
        'label'     => __('common.workflow_stepper_submitted'),
        'icon'      => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'state'     => 'completed',
        'timestamp' => $submitTs ? \Illuminate\Support\Carbon::parse($submitTs)->format('d M Y H:i') : null,
    ]];

    // step 1..N: workflow stages
    foreach ($instance->steps as $i => $step) {
        $isStepApproved = $step->action === 'approved';
        $isStepRejected = $step->action === 'rejected';
        $isStepCurrent  = $isPending && (int) $step->step_no === (int) $instance->current_step_no;

        $state = $isStepApproved ? 'completed' : ($isStepRejected ? 'rejected' : ($isStepCurrent ? 'active' : 'pending'));

        // pick representative timestamp
        $stepTs = null;
        if ($isStepApproved && ! empty($step->approved_by)) {
            // Copy to a local var first — end() takes its arg by reference and
            // can't modify a magic/overloaded model attribute in place.
            $approvedBy = $step->approved_by;
            $last = end($approvedBy);
            // `at` is stored as an ISO8601 string (now()->toIso8601String()) —
            // always reformat it to the human format, same as the submit step.
            $rawAt = $last['at'] ?? null;
            if ($rawAt) {
                try { $stepTs = \Illuminate\Support\Carbon::parse($rawAt)->format('d M Y H:i'); } catch (\Throwable $e) {}
            }
        } elseif ($isStepRejected) {
            $stepTs = optional($step->updated_at)->format('d M Y H:i');
        }

        $stepperSteps[] = [
            'label'     => $step->stage_name,
            'icon'      => $isStepRejected ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7',
            'state'     => $state,
            'timestamp' => $stepTs,
        ];
    }

    // final step: completion
    $finalState = $isApproved ? 'completed' : ($isRejected ? 'rejected' : 'pending');
    $stepperSteps[] = [
        'label'     => $isRejected
            ? __('common.workflow_stepper_rejected')
            : __('common.workflow_stepper_completed'),
        'icon'      => $isRejected
            ? 'M6 18L18 6M6 6l12 12'
            : 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'state'     => $finalState,
        'timestamp' => $isApproved && optional($instance->updated_at)
            ? $instance->updated_at->format('d M Y H:i')
            : null,
    ];
@endphp

{{-- Horizontal stepper (desktop) / horizontally-scrollable on mobile --}}
<div class="overflow-x-auto -mx-2 px-2 pb-1">
    <div class="flex items-start min-w-max sm:min-w-0" style="--step-count: {{ count($stepperSteps) }}">
        @foreach($stepperSteps as $i => $st)
            @php
                $circleClass = match($st['state']) {
                    'completed' => 'bg-blue-600 text-white border-blue-600',
                    'active'    => 'bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 border-blue-500 dark:border-blue-400 ring-4 ring-blue-100 dark:ring-blue-900/40 animate-pulse',
                    'rejected'  => 'bg-red-500 text-white border-red-500',
                    default     => 'bg-white dark:bg-slate-800 text-slate-300 dark:text-slate-600 border-slate-300 dark:border-slate-600',
                };
                $labelClass = match($st['state']) {
                    'completed', 'rejected' => 'text-slate-700 dark:text-slate-200',
                    'active'                => 'text-slate-900 dark:text-slate-50 font-semibold',
                    default                 => 'text-slate-400 dark:text-slate-500',
                };
                // Connector to next step: colored when this step is completed/rejected
                $connectorClass = match($st['state']) {
                    'completed', 'rejected' => ($st['state'] === 'rejected' ? 'bg-red-500' : 'bg-blue-500'),
                    default                 => 'bg-slate-200 dark:bg-slate-700',
                };
            @endphp
            <div class="flex flex-col items-center text-center px-2" style="flex: 1 1 0; min-width: 96px">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full border-2 flex items-center justify-center transition-all {{ $circleClass }}">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $st['icon'] }}"/>
                    </svg>
                </div>
                <div class="mt-2 text-xs sm:text-sm leading-tight {{ $labelClass }}">{{ $st['label'] }}</div>
                @if(!empty($st['timestamp']))
                    <div class="text-[10px] sm:text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $st['timestamp'] }}</div>
                @endif
            </div>
            @if(!$loop->last)
                <div class="h-0.5 mt-6 sm:mt-7 {{ $connectorClass }}" style="flex: 1 1 0; min-width: 32px"></div>
            @endif
        @endforeach
    </div>
</div>
