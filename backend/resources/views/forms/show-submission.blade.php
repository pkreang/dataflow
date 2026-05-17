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
        @php $instance = $submission->instance; @endphp
        <div class="card p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $instance->workflow?->name ?? '—' }}</h3>
                    @if($instance->status === 'pending')
                        <span class="badge-blue">{{ __('common.approval_status_' . $instance->status) }}</span>
                    @elseif($instance->status === 'approved')
                        <span class="badge-green">{{ __('common.approval_status_' . $instance->status) }}</span>
                    @elseif($instance->status === 'rejected')
                        <span class="badge-red">{{ __('common.approval_status_' . $instance->status) }}</span>
                    @else
                        <span class="badge-gray">{{ __('common.approval_status_' . $instance->status) }}</span>
                    @endif
                </div>
            </div>

            @if($instance->steps->count())
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($instance->steps as $step)
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm
                            {{ $step->action === 'approved' ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300' :
                               ($step->action === 'rejected' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' :
                                'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400') }}">
                            <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                {{ $step->action === 'approved' ? 'bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-200' :
                                   ($step->action === 'rejected' ? 'bg-red-200 dark:bg-red-800 text-red-800 dark:text-red-200' :
                                    'bg-slate-300 dark:bg-slate-600 text-slate-700 dark:text-slate-300') }}">
                                {{ $step->step_no }}
                            </span>
                            <span class="font-medium">{{ $step->stage_name }}</span>
                            @if(($step->min_approvals ?? 1) > 1)
                                <span class="opacity-75">({{ count($step->approved_by ?? []) }}/{{ $step->min_approvals }})</span>
                            @endif
                            <span class="text-xs opacity-75">{{ __('common.approval_status_' . $step->action) }}</span>
                        </div>
                        @if(!$loop->last)
                            <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        @endif
                    @endforeach
                </div>
            @endif
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

    {{-- Activity log (audit trail) --}}
    @if(isset($activity) && $activity->isNotEmpty())
        <div class="card p-4 mb-6">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">{{ __('common.submission_activity') }}</h3>
            <ul class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
                @foreach($activity as $log)
                    <li class="py-2 flex items-center justify-between gap-3">
                        <div>
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ __('common.activity_'.$log->action) }}</span>
                            @if($log->user)
                                <span class="text-slate-500 dark:text-slate-400"> — {{ $log->user->full_name }}</span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
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
