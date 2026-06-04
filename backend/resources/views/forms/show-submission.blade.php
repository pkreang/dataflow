@extends($layout ?? 'layouts.app')

@section('title', $submission->form->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => __('common.my_submissions'), 'url' => route('forms.my-submissions')],
        ['label' => __('common.view')],
    ]" />
@endsection

@section('content')
<div style="width:100%;max-width:100%">
    <div class="mb-6">
        <a href="{{ route('forms.my-submissions') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.my_submissions') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ $submission->form->name }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ $submission->reference_no ?: ('#' . $submission->id) }}
            @if($submission->instance)
                · {{ __('common.approval_status_' . $submission->instance->status) }}
            @endif
        </p>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert-warning mb-4">
            {{ session('warning') }}
        </div>
    @endif

    {{-- Post-action evaluation CTA — owner of approved work, not an eval itself --}}
    @php
        $viewerUserId = (int) (session('user.id') ?? 0);
        $isOwnerView = (int) $submission->user_id === $viewerUserId;
        $isEvalReady = $submission->parent_submission_id === null
            && $isOwnerView
            && ! $submission->trashed()
            && $submission->effective_status === 'approved'
            && (bool) ($submission->form?->evaluation_enabled ?? false)
            && app(\App\Services\EvaluationFormResolver::class)->hasFormFor($submission);
        $existingEval = $isEvalReady ? $submission->evaluations()->first() : null;
    @endphp
    @if ($isEvalReady)
        <div class="card border-l-4 border-emerald-500 p-4 mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ $existingEval ? __('common.evaluation_already_submitted') : __('common.evaluation_cta_title') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.evaluation_cta_desc') }}</p>
            </div>
            <a href="{{ $existingEval ? route('forms.submission.show', $existingEval) : route('forms.submission.evaluate', $submission) }}"
               class="btn-primary shrink-0">
                {{ $existingEval ? __('common.view_evaluation') : __('common.action_evaluate') }}
            </a>
        </div>
    @endif

    @if ($submission->trashed())
        @php $submission->load('deleter'); @endphp
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/20 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4.99a2 2 0 00-3.46 0L3.2 16A2 2 0 004.93 19z"/></svg>
                <div>
                    <p class="font-medium">{{ __('common.approval_status_cancelled') }}</p>
                    <p class="mt-0.5">
                        {{ __('common.submission_cancelled_banner', [
                            'at' => $submission->deleted_at?->format('d M Y H:i') ?? '—',
                            'by' => $submission->deleter ? trim(($submission->deleter->first_name ?? '').' '.($submission->deleter->last_name ?? '')) : __('common.system'),
                        ]) }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Workflow status — compact horizontal bar on top --}}
    @if($submission->instance)
        @php
            $instance = $submission->instance;
            // Build stepper data: submit → workflow stages → final outcome
            $isApproved = $instance->status === 'approved';
            $isRejected = $instance->status === 'rejected';
            $isPending  = $instance->status === 'pending';

            // step 0: submitted
            $submitTs = optional($submission->submittedActivity?->created_at ?? $submission->created_at);
            $stepperSteps = [[
                'label'     => __('common.workflow_stepper_submitted'),
                'icon'      => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'state'     => 'completed',
                'timestamp' => $submitTs ? $submitTs->format('d M Y H:i') : null,
            ]];

            // step 1..N: workflow stages
            $rejectedAtStep = null;
            foreach ($instance->steps as $i => $step) {
                $isStepApproved = $step->action === 'approved';
                $isStepRejected = $step->action === 'rejected';
                $isStepCurrent  = $isPending && (int) $step->step_no === (int) $instance->current_step_no;
                if ($isStepRejected) $rejectedAtStep = $i;

                $state = $isStepApproved ? 'completed' : ($isStepRejected ? 'rejected' : ($isStepCurrent ? 'active' : 'pending'));

                // pick representative timestamp
                $stepTs = null;
                if ($isStepApproved && !empty($step->approved_by)) {
                    $last = end($step->approved_by);
                    $stepTs = $last['at'] ?? null;
                } elseif ($isStepRejected) {
                    $stepTs = optional($step->updated_at)->format('d M Y H:i');
                }
                if ($stepTs && !str_contains($stepTs, ':')) {
                    try { $stepTs = \Carbon\Carbon::parse($stepTs)->format('d M Y H:i'); } catch (\Throwable $e) {}
                }

                $stepperSteps[] = [
                    'label'     => $step->stage_name,
                    'icon'      => $isStepRejected
                        ? 'M6 18L18 6M6 6l12 12'
                        : 'M5 13l4 4L19 7',
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
        <div class="card p-5 sm:p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ $instance->workflow?->name ?? '—' }}</h3>
                @if($isPending)
                    <span class="badge-blue">{{ __('common.approval_status_' . $instance->status) }}</span>
                @elseif($isApproved)
                    <span class="badge-green">{{ __('common.approval_status_' . $instance->status) }}</span>
                @elseif($isRejected)
                    <span class="badge-red">{{ __('common.approval_status_' . $instance->status) }}</span>
                @else
                    <span class="badge-gray">{{ __('common.approval_status_' . $instance->status) }}</span>
                @endif
            </div>

            {{-- Horizontal stepper (desktop) / vertical stack (mobile via overflow-x-auto) --}}
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
                            // Connector to next step: green when this step is completed
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
        </div>
    @endif

    {{-- Assigned editors (owner / super-admin only, draft only) + read-only
         visibility of the current list to anyone who can see the submission --}}
    @if(($canManageAssignedEditors ?? false) || !empty($assignedEditorRows ?? []))
        <div class="card p-4 mb-6"
             @if(($canManageAssignedEditors ?? false))
                 x-data="assignedEditorsPicker(@js($assignedEditorRows ?? []), @js($assignableUsers ?? []))"
             @endif>
            <div class="flex items-center justify-between gap-3 mb-2">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('common.assigned_editors_title') }}</h3>
                @if(($canManageAssignedEditors ?? false))
                    <button type="button" class="text-xs text-blue-600 hover:underline" @click="open = !open">
                        <span x-show="!open">{{ __('common.assigned_editors_manage') }}</span>
                        <span x-show="open" x-cloak>{{ __('common.cancel') }}</span>
                    </button>
                @endif
            </div>

            @if(($canManageAssignedEditors ?? false))
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('common.assigned_editors_help') }}</p>

                <div class="flex flex-wrap items-center gap-1">
                    <template x-for="u in selected" :key="u.id">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs">
                            <span x-text="u.name"></span>
                            <button type="button" x-show="open" x-cloak class="text-blue-500 hover:text-red-600" @click="remove(u.id)">×</button>
                        </span>
                    </template>
                    <span x-show="!selected.length" class="text-xs text-slate-400 dark:text-slate-500">{{ __('common.assigned_editors_none') }}</span>
                </div>

                <div x-show="open" x-cloak class="mt-2 p-2 border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-800">
                    <input type="text" x-model="query" placeholder="{{ __('common.field_editable_by_users_search') }}"
                           class="form-input py-1 px-2 text-xs w-full mb-1" />
                    <div class="max-h-40 overflow-y-auto">
                        <template x-for="u in available()" :key="u.id">
                            <button type="button"
                                    class="block w-full text-left text-xs px-2 py-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300"
                                    @click="add(u); query = ''">
                                <span x-text="u.name"></span>
                            </button>
                        </template>
                        <p x-show="!available().length" class="text-xs text-slate-400 dark:text-slate-500 px-2 py-1">
                            {{ __('common.field_editable_by_users_empty') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('forms.submission.assigned-editors.update', $submission) }}" class="mt-2 flex justify-end gap-2">
                        @csrf
                        <template x-for="u in selected" :key="'inp-' + u.id">
                            <input type="hidden" name="user_ids[]" :value="u.id">
                        </template>
                        <button type="submit" class="btn-primary text-xs">{{ __('common.save') }}</button>
                    </form>
                </div>
            @else
                {{-- Read-only view for non-owners (e.g. assignees viewing the list) --}}
                <div class="flex flex-wrap items-center gap-1">
                    @foreach($assignedEditorRows ?? [] as $row)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-xs">
                            {{ $row['name'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Activity log (audit trail) — timeline style with action-typed icons + color border --}}
    @if(isset($activity) && $activity->isNotEmpty())
        <div class="card p-4 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                {{ __('common.submission_activity') }}
            </h3>
            <ul class="space-y-2">
                @foreach($activity as $log)
                    @php
                        // Map action → icon SVG path + color theme
                        $iconMap = match($log->action) {
                            'submitted' => ['M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8', 'blue'],
                            'approved' => ['M5 13l4 4L19 7', 'green'],
                            'rejected' => ['M6 18L18 6M6 6l12 12', 'red'],
                            'returned', 'sent_back' => ['M11 17l-5-5m0 0l5-5m-5 5h12', 'amber'],
                            'cancelled' => ['M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636', 'slate'],
                            'draft_created', 'created' => ['M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'slate'],
                            default => ['M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'slate'],
                        };
                        [$iconPath, $color] = $iconMap;
                    @endphp
                    <li class="flex items-start gap-3 p-2 rounded-lg border-l-4 border-{{ $color }}-400 dark:border-{{ $color }}-500 bg-{{ $color }}-50/40 dark:bg-{{ $color }}-900/10">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $color }}-100 dark:bg-{{ $color }}-900/50 flex items-center justify-center text-{{ $color }}-600 dark:text-{{ $color }}-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ __('common.activity_'.$log->action) }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                @if($log->user)
                                    <span>{{ $log->user->full_name }}</span>
                                    <span class="opacity-60"> · </span>
                                @endif
                                <span title="{{ $log->created_at->format('Y-m-d H:i:s') }}">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form payload (read-only) — full width --}}
    <div class="card p-4 sm:p-6 lg:p-8">
        @php $form = $submission->form; @endphp
        <x-document-form-fields-grid :columns="$form->layout_columns ?? 1">
            @foreach($form->fields as $field)
                @php
                    $fKey   = $field->field_key;
                    $fName  = "fields[{$fKey}]";
                    $fValue = $submission->payload[$fKey] ?? null;
                    $fSpan  = ($field->col_span && ($form->layout_columns ?? 1) > 1)
                        ? min($field->col_span, $form->layout_columns)
                        : 1;
                @endphp
                <div @if($fSpan > 1) style="grid-column: span {{ $fSpan }}" @endif>
                    @if($field->field_type !== 'section')
                        <label class="block text-sm text-slate-500 dark:text-slate-400 mb-1">
                            {{ $field->localized_label }}
                        </label>
                    @endif
                    @php
                        $qrPayloadForField = ($field->field_type === 'qr_code')
                            ? \App\Support\QrTemplateResolver::resolve(
                                (string) ((is_array($field->options) ? $field->options : [])['template'] ?? ''),
                                $submission
                            )
                            : null;
                    @endphp
                    @include('components.dynamic-field', [
                        'field'       => $field,
                        'name'        => $fName,
                        'value'       => $fValue,
                        'editorRole'  => 'view_only',
                        'referenceNo' => $submission->reference_no,
                        'qrPayload'   => $qrPayloadForField,
                    ])
                </div>
            @endforeach
        </x-document-form-fields-grid>
    </div>

    @if($submission->instance && $submission->instance->steps->isNotEmpty())
        @php
            $signatureRows = [];
            foreach ($submission->instance->steps as $step) {
                if ($step->action === 'approved') {
                    foreach (($step->approved_by ?? []) as $entry) {
                        $signatureRows[] = [
                            'step' => $step->step_no,
                            'stage' => $step->stage_name,
                            'name' => $entry['name'] ?? '—',
                            'signature' => $entry['signature'] ?? null,
                            'at' => $entry['at'] ?? null,
                            'action' => 'approved',
                        ];
                    }
                } elseif ($step->action === 'rejected') {
                    $rejector = \App\Models\User::find($step->acted_by_user_id);
                    $signatureRows[] = [
                        'step' => $step->step_no,
                        'stage' => $step->stage_name,
                        'name' => $rejector?->full_name ?? '—',
                        'signature' => $step->signature_image,
                        'at' => $step->acted_at?->toIso8601String(),
                        'action' => 'rejected',
                    ];
                }
            }
        @endphp
        @if(! empty($signatureRows))
            <div class="card p-4 sm:p-6 mt-6">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('common.authorized_signatures') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800">
                                <th class="border border-slate-300 dark:border-slate-600 px-2 py-1 w-12 text-center">{{ __('common.workflow_step_short') }}</th>
                                <th class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-left">{{ __('common.workflow_stage_name') }}</th>
                                <th class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-left">{{ __('common.name') }}</th>
                                <th class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-center" style="width:220px">{{ __('common.signature') }}</th>
                                <th class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-left" style="width:130px">{{ __('common.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($signatureRows as $row)
                                <tr>
                                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-center font-semibold">{{ $row['step'] }}</td>
                                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-1">{{ $row['stage'] }}</td>
                                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-1">
                                        {{ $row['name'] }}
                                        @if($row['action'] === 'rejected')
                                            <div class="text-[10px] text-red-600 dark:text-red-400">{{ __('common.approval_status_rejected') }}</div>
                                        @endif
                                    </td>
                                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-1 text-center" style="height:70px">
                                        @if(! empty($row['signature']))
                                            <img src="{{ $row['signature'] }}" alt="" class="inline-block max-h-14 max-w-[200px] object-contain">
                                        @endif
                                    </td>
                                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-1">
                                        {{ $row['at'] ? \Carbon\Carbon::parse($row['at'])->format('d M Y H:i') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>

@if(($canManageAssignedEditors ?? false))
<script>
    function assignedEditorsPicker(initialSelected, pool) {
        return {
            open: false,
            query: '',
            selected: Array.isArray(initialSelected) ? [...initialSelected] : [],
            pool: Array.isArray(pool) ? pool : [],
            available() {
                const q = (this.query || '').toLowerCase().trim();
                const selectedIds = new Set(this.selected.map((u) => u.id));
                let list = this.pool.filter((u) => !selectedIds.has(u.id));
                if (q) list = list.filter((u) => (u.name || '').toLowerCase().includes(q));
                return list.slice(0, 25);
            },
            add(user) {
                if (!this.selected.find((u) => u.id === user.id)) {
                    this.selected.push(user);
                }
            },
            remove(userId) {
                this.selected = this.selected.filter((u) => u.id !== userId);
            },
        };
    }
</script>
@endif
@endsection
