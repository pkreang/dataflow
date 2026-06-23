@extends('layouts.app')

@section('title', $form->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => $form->name_th ?? $form->name_en ?? $form->name],
    ]" />
@endsection

@php
    $relatedApproverSet = array_flip($relatedInstanceIds ?? []);
    $viewer = [
        'id'             => (int) (session('user.id') ?? 0),
        'is_super_admin' => (bool) session('user.is_super_admin', false),
    ];

    // Pre-resolve lookup items (one query per lookup source, shared across rows)
    $lookupMaps = [];
    foreach ($searchable as $f) {
        $src = (is_array($f->options) && !empty($f->options['source'])) ? $f->options['source'] : null;
        if ($src && in_array($f->field_type, ['lookup', 'multi_select'], true)) {
            $items = \App\Support\LookupRegistry::getItems($src);
            $map = [];
            foreach ($items as $it) {
                $map[(string) ($it['value'] ?? '')] = $it['display'] ?? $it['value'] ?? '';
            }
            $lookupMaps[$f->field_key] = $map;
        }
    }

    $renderCell = function ($submission, $field) use ($lookupMaps) {
        $raw = $submission->payload[$field->field_key] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return '—';
        }
        if ($field->field_type === 'multi_select' && is_array($raw)) {
            $map = $lookupMaps[$field->field_key] ?? null;
            $labels = array_map(fn ($v) => $map[(string) $v] ?? (string) $v, $raw);
            return implode(', ', $labels);
        }
        return match (true) {
            $field->field_type === 'lookup' => $lookupMaps[$field->field_key][(string) $raw] ?? (string) $raw,
            in_array($field->field_type, ['date'], true) => \Illuminate\Support\Carbon::parse((string) $raw)->format('Y-m-d'),
            $field->field_type === 'datetime' => \Illuminate\Support\Carbon::parse((string) $raw)->format('Y-m-d H:i'),
            $field->field_type === 'checkbox' => is_array($raw) ? implode(', ', $raw) : (string) $raw,
            default => is_array($raw) ? implode(', ', $raw) : (string) $raw,
        };
    };

    $statusBadgeMap = [
        'draft' => 'badge-yellow',
        'pending' => 'badge-blue',
        'approved' => 'badge-green',
        'rejected' => 'badge-red',
        'submitted' => 'badge-gray',
        'cancelled' => 'badge-gray',
    ];

    $isEvalList = $form->document_type === 'evaluation';

    $tableColumns = array_merge(
        [['key' => 'seq', 'label' => '#', 'class' => 'text-right w-12']],
        [['key' => 'reference_no', 'label' => __('common.reference_no')]],
        $isEvalList ? [['key' => 'parent', 'label' => __('common.evaluation_source_doc')]] : [],
        collect($searchable)->map(fn ($f) => ['key' => 'f_'.$f->field_key, 'label' => $f->localized_label])->all(),
        [
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'last_activity', 'label' => __('common.last_activity')],
            ['key' => 'created_at', 'label' => __('common.created_at')],
            ['key' => 'submitted_at', 'label' => __('common.submitted_at')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ],
    );
    // Drafts need a checkbox column for bulk delete
    $hasDrafts = $submissions->contains(fn ($s) => $s->status === 'draft');
    if ($hasDrafts) {
        array_unshift($tableColumns, ['key' => 'select', 'label' => '', 'class' => 'w-8']);
    }
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $form->name }}</h2>
            @if($form->description)
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $form->description }}</p>
            @endif
        </div>
        @unless($isEvalList)
            <a href="{{ route('forms.create', $form->form_key) }}" class="btn-primary">
                {{ __('common.create') }}
            </a>
        @endunless
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <form method="GET" class="card p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                    {{ __('common.reference_no') }}
                </label>
                <input type="text" name="reference_no" value="{{ $filters['reference_no'] ?? '' }}" class="form-input">
            </div>
            @foreach($searchable as $field)
                @include('forms._filter-input', ['field' => $field, 'filters' => $filters])
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">{{ __('common.search') }}</button>
            <a href="{{ route('forms.list-by-form', $form->form_key) }}" class="btn-secondary">{{ __('common.reset') }}</a>
            <label class="ml-auto inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                <input type="checkbox" name="show_cancelled" value="1" @checked($showCancelled ?? false)
                       onchange="this.form.submit()"
                       class="rounded border-slate-300 dark:border-slate-600">
                {{ __('common.show_cancelled_toggle') }}
            </label>
        </div>
    </form>

    <form method="POST" action="{{ route('forms.submissions.bulk-delete-drafts') }}"
          onsubmit="return confirm('{{ __('common.bulk_delete_confirm') }}')"
          x-data="{ selected: [], get hasSelection() { return this.selected.length > 0; } }">
        @csrf

        {{-- Bulk toolbar (shows only when something is selected) --}}
        <div x-show="hasSelection" x-cloak class="flex items-center gap-2 mb-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900 rounded-lg text-sm">
            <span class="text-amber-800 dark:text-amber-200" x-text="`{{ __('common.bulk_selected_label') }} ${selected.length}`"></span>
            <div class="flex-1"></div>
            <button type="submit" class="btn-danger text-sm">{{ __('common.action_delete_draft') }}</button>
        </div>

        <x-data-table :columns="$tableColumns" :rows="$submissions" :disable-pagination="true"
                      :empty-message="$isEvalList ? __('common.evaluation_list_empty') : __('common.no_submissions_yet')">
            @foreach($submissions as $submission)
                @php
                    $rowIsRelatedApprover = $submission->approval_instance_id !== null
                        && isset($relatedApproverSet[(int) $submission->approval_instance_id]);
                    $rowViewer = array_merge($viewer, ['is_related_approver' => $rowIsRelatedApprover]);
                    $plan = $submission->actionPlan($rowViewer);
                    $status = $submission->effective_status;
                    // Row link goes to the submission's view page regardless of primary button.
                    $viewMenuItem = collect($plan['menu'])->firstWhere('label', __('common.view'));
                    $rowHref = $viewMenuItem['href'] ?? ($plan['primary']['href'] ?? null);
                    $statusBadge = $statusBadgeMap[$status] ?? 'badge-gray';
                    $la = $submission->latestActivity;
                    // Row surfaced because the viewer approves it (not owns/edits it).
                    $isMineRow = (int) $submission->user_id === $viewer['id']
                        || $submission->isAssignedEditor($viewer['id']);
                @endphp
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                    @if($hasDrafts)
                        <td class="px-4 py-2 whitespace-nowrap">
                            @if($submission->status === 'draft')
                                <input type="checkbox" name="ids[]" value="{{ $submission->id }}"
                                       x-model="selected"
                                       class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-2 whitespace-nowrap text-right text-xs text-slate-500 dark:text-slate-400">
                        {{ ($submissions->currentPage() - 1) * $submissions->perPage() + $loop->iteration }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        @if($rowHref)
                            <a href="{{ $rowHref }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $submission->reference_no ?: ('#' . $submission->id) }}
                            </a>
                        @else
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ $submission->reference_no ?: ('#' . $submission->id) }}
                            </span>
                        @endif
                    </td>
                    @if($isEvalList)
                        <td class="table-sub">
                            @if($submission->originalSubmission)
                                <a href="{{ route('forms.submission.show', $submission->originalSubmission) }}"
                                   class="text-blue-600 dark:text-blue-400 hover:underline text-sm font-medium">
                                    {{ $submission->originalSubmission->reference_no ?: '#'.$submission->originalSubmission->id }}
                                </a>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $submission->originalSubmission->form?->name }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    @endif
                    @foreach($searchable as $field)
                        <td class="table-sub">{{ $renderCell($submission, $field) }}</td>
                    @endforeach
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                            {{ __('common.approval_status_' . $status) }}
                        </span>
                    </td>
                    <td class="table-sub">
                        @if($la)
                            <span>{{ __('common.activity_'.$la->action) }}</span>
                            @if($la->user)
                                <span class="text-slate-400"> · {{ $la->user->first_name }} {{ $la->user->last_name }}</span>
                            @endif
                            <p class="text-xs text-slate-400">{{ $la->created_at->diffForHumans() }}</p>
                        @else
                            —
                        @endif
                    </td>
                    <td class="table-sub whitespace-nowrap">{{ $submission->created_at->format('d M Y H:i') }}</td>
                    <td class="table-sub whitespace-nowrap">
                        {{ $submission->submittedActivity?->created_at?->format('d M Y H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-right">
                        @if(!empty($plan['menu']))
                            <div data-row-action class="flex items-center justify-end">
                                <x-row-actions :items="$plan['menu']" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-per-page-footer :paginator="$submissions" :perPage="$perPage" id="list-by-form-pagination" />
    </form>
@endsection
