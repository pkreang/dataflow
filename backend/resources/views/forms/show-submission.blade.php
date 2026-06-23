@extends($layout ?? 'layouts.app')

@section('title', $submission->form->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => $submission->form->name, 'url' => route('forms.list-by-form', $submission->form)],
        ['label' => __('common.view')],
    ]" />
@endsection

@section('content')
<div style="width:100%;max-width:100%">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('forms.list-by-form', $submission->form) }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ $submission->form->name }}</a>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ $submission->form->name }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $submission->reference_no ?: ('#' . $submission->id) }}
                @if($submission->instance)
                    · {{ __('common.approval_status_' . $submission->instance->status) }}
                @endif
            </p>
            @if($submission->isOnBehalf())
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                    {{ __('common.submitted_on_behalf_by', [
                        'creator' => $submission->createdBy?->full_name ?? '—',
                        'owner' => $submission->user?->full_name ?? '—',
                    ]) }}
                </p>
            @endif
        </div>
        @if($submission->status !== 'draft')
        <div class="flex gap-2 shrink-0">
            <a href="{{ route('forms.submission.print', $submission) }}" target="_blank"
               class="btn-secondary text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                {{ __('common.action_print') }}
            </a>
            <a href="{{ route('forms.submission.pdf', $submission) }}"
               class="btn-secondary text-sm flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('common.download_pdf') ?? 'ดาวน์โหลด PDF' }}
            </a>
        </div>
        @endif
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

    @if ($errors->has('approval'))
        <div class="alert-error mb-4">{{ $errors->first('approval') }}</div>
    @endif

    @if ($errors->has('send_back'))
        <div class="alert-error mb-4">{{ $errors->first('send_back') }}</div>
    @endif

    {{-- Catch-all: surface ANY other validation error so an approval/field action
         can never fail silently (e.g. oversize signature, missing action). --}}
    @php
        $shownErrorKeys = ['approval', 'send_back'];
        $otherErrorKeys = collect($errors->keys())->reject(fn ($k) => in_array($k, $shownErrorKeys, true));
    @endphp
    @if ($otherErrorKeys->isNotEmpty())
        <div class="alert-error mb-4">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($otherErrorKeys as $errKey)
                    <li>{{ $errors->first($errKey) }}</li>
                @endforeach
            </ul>
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
            $isApproved = $instance->status === 'approved';
            $isRejected = $instance->status === 'rejected';
            $isPending  = $instance->status === 'pending';
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

            <x-approval-stepper :instance="$instance"
                :submitted-at="$submission->submittedActivity?->created_at ?? $submission->created_at" />
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

    {{-- Form payload — split into approver section + main section when approver is editing --}}
    @php
        $form = $submission->form;
        $isApproverEditing = ($editorRole ?? 'view_only') !== 'view_only'
            && $submission->instance
            && $form->fields->contains(fn ($f) =>
                $f->field_type !== 'file'
                && (in_array($editorRole, $f->effective_editable_by, true)
                    || (($editorUserId ?? null) && in_array('user:'.$editorUserId, $f->effective_editable_by, true))));

        // Partition fields: approver-editable vs read-only remainder
        $approverEditableFieldIds = $isApproverEditing
            ? $form->fields->filter(fn ($f) =>
                $f->field_type !== 'file'
                && (in_array($editorRole, $f->effective_editable_by, true)
                    || (($editorUserId ?? null) && in_array('user:'.$editorUserId, $f->effective_editable_by, true))))
                ->pluck('id')->all()
            : [];

        // Required fields for the current step (for server-side error display + client guard)
        $currentStepNo = str_starts_with($editorRole ?? '', 'step_') ? (int) substr($editorRole, 5) : null;
        $approverRequiredFields = $currentStepNo
            ? $form->fields->filter(fn ($f) => in_array($currentStepNo, $f->required_at_step ?? []))
                ->map(fn ($f) => ['key' => $f->field_key, 'label' => $f->localized_label])
                ->values()->all()
            : [];
    @endphp

    @if($isApproverEditing)
        {{-- Approver section — editable fields in a highlighted card --}}
        <div class="approver-section-card card border-2 border-blue-200 dark:border-blue-700 bg-blue-50/40 dark:bg-blue-900/10 p-4 sm:p-6 mb-4">
            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-0.5">{{ __('common.approver_section_title') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('common.approver_section_hint') }}</p>

            @if($errors->has('approve'))
                <div class="alert-error mb-3 text-sm">{{ $errors->first('approve') }}</div>
            @endif

            <form method="POST" action="{{ route('approvals.update-fields', $submission->instance) }}" novalidate>
                @csrf @method('PATCH')
                <x-document-form-fields-grid :columns="$form->layout_columns ?? 1">
                    @foreach($form->fields->filter(fn ($f) => in_array($f->id, $approverEditableFieldIds)) as $field)
                        @php
                            $fKey   = $field->field_key;
                            $fValue = $submission->payload[$fKey] ?? null;
                            $fSpan  = ($field->col_span && ($form->layout_columns ?? 1) > 1)
                                ? min($field->col_span, $form->layout_columns)
                                : 1;
                            $isStepRequired = $currentStepNo && in_array($currentStepNo, $field->required_at_step ?? []);
                        @endphp
                        <div @if($fSpan > 1) style="grid-column: span {{ $fSpan }}" @endif>
                            @if($field->field_type !== 'section')
                                <label class="block text-sm text-slate-500 dark:text-slate-400 mb-1">
                                    {{ $field->localized_label }}
                                    @if($isStepRequired)
                                        <span class="text-red-500 ml-0.5">*</span>
                                    @endif
                                </label>
                            @endif
                            @include('components.dynamic-field', [
                                'field'        => $field,
                                'name'         => "field_updates[{$fKey}]",
                                'value'        => $fValue,
                                'editorRole'   => $editorRole,
                                'editorUserId' => $editorUserId ?? null,
                                'userOrgUnitId' => $userOrgUnitId ?? null,
                                'referenceNo'  => $submission->reference_no,
                                'qrPayload'    => null,
                            ])
                        </div>
                    @endforeach
                </x-document-form-fields-grid>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn-primary">{{ __('common.save_fields') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Main form — all remaining fields (read-only) --}}
    @php $remainingFields = $form->fields->filter(fn ($f) => !in_array($f->id, $approverEditableFieldIds)); @endphp
    @if($remainingFields->isNotEmpty())
    <div class="card p-4 sm:p-6 lg:p-8">
        <x-document-form-fields-grid :columns="$form->layout_columns ?? 1">
            @foreach($remainingFields as $field)
                @php
                    $fKey   = $field->field_key;
                    $fValue = $submission->payload[$fKey] ?? null;
                    $fSpan  = ($field->col_span && ($form->layout_columns ?? 1) > 1)
                        ? min($field->col_span, $form->layout_columns)
                        : 1;
                    $qrPayloadForField = ($field->field_type === 'qr_code')
                        ? \App\Support\QrTemplateResolver::resolve(
                            (string) ((is_array($field->options) ? $field->options : [])['template'] ?? ''),
                            $submission
                        )
                        : null;
                @endphp
                <div @if($fSpan > 1) style="grid-column: span {{ $fSpan }}" @endif>
                    @if($field->field_type !== 'section')
                        <label class="block text-sm text-slate-500 dark:text-slate-400 mb-1">
                            {{ $field->localized_label }}
                        </label>
                    @endif
                    @include('components.dynamic-field', [
                        'field'        => $field,
                        'name'         => "fields[{$fKey}]",
                        'value'        => $fValue,
                        'editorRole'   => 'view_only',
                        'editorUserId' => $editorUserId ?? null,
                                'userOrgUnitId' => $userOrgUnitId ?? null,
                        'referenceNo'  => $submission->reference_no,
                        'qrPayload'    => $qrPayloadForField,
                    ])
                </div>
            @endforeach
        </x-document-form-fields-grid>
    </div>
    @endif

    {{-- Inject required-at-step metadata for client-side guard in approval-action --}}
    @if(($canAct ?? false) && !empty($approverRequiredFields))
    <script>
        window.__approverRequiredFields__ = @json($approverRequiredFields);
    </script>
    @endif

    {{-- Approval action — comment + signature + approve/reject (+ send-back).
         Gated on $canAct (status pending + approval.approve permission + current
         approver) so it never renders for someone whose POST would 403. --}}
    @if($canAct ?? false)
        <x-approval-action :instance="$submission->instance" />
    @endif

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
