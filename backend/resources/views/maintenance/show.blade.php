@extends('layouts.app')

@section('title', __('common.pm_am_plan_detail'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.maintenance'), 'url' => route('maintenance.index')],
        ['label' => __('common.pm_am_plan_detail')],
    ]" />
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('maintenance.index') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ __('common.pm_am_plan_detail') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap items-center gap-2">
                <span>{{ $instance->reference_no ?: ('#' . $instance->id) }}</span>
                @php $s = $instance->status; @endphp
                @if($s === 'approved')
                    <span class="badge-green">{{ __('common.approval_status_' . $s) }}</span>
                @elseif($s === 'rejected')
                    <span class="badge-red">{{ __('common.approval_status_' . $s) }}</span>
                @else
                    <span class="badge-yellow">{{ __('common.approval_status_' . $s) }}</span>
                @endif
            </p>
        </div>
        @if($instance->status === 'approved')
            <a href="{{ route('spare-parts.requisition.create', ['parent_type' => 'pm_am_plan', 'parent_id' => $instance->id]) }}"
               class="btn-primary">
                {{ __('common.request_spare_parts') }}
            </a>
        @endif
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @include('repair-requests._company_header', ['company' => $company ?? null, 'branch' => $branch ?? null])

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-5 space-y-4">
            <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.payload_summary') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.reference_no') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100 font-medium">{{ $instance->reference_no ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.department') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $instance->orgUnit?->name ?? $instance->department?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.user') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $instance->requester?->full_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.workflow_name') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $instance->workflow?->name ?? '—' }}</dd>
                </div>
            </dl>
            @if($formFields->count())
                <div class="border-t border-slate-200 dark:border-slate-600 pt-4">
                    @if($editorRole !== 'view_only')
                    <form method="POST" action="{{ route('approvals.update-fields', $instance) }}" novalidate>
                        @csrf @method('PATCH')
                    @endif
                    <x-document-form-fields-grid :columns="$formForLabels->layout_columns ?? 1">
                    @foreach($formFields as $field)
                        @php
                            $fValue = $instance->payload[$field->field_key] ?? null;
                            $fName  = "field_updates[{$field->field_key}]";
                            $fSpan  = ($field->col_span && ($formForLabels->layout_columns ?? 1) > 1)
                                ? min($field->col_span, $formForLabels->layout_columns)
                                : 1;
                        @endphp
                        <div @if($fSpan > 1) style="grid-column: span {{ $fSpan }}" @endif>
                            @if($field->field_type !== 'section')
                                <label class="form-label">
                                    {{ $field->label }}
                                    @if($field->is_required) <span class="text-red-500">*</span> @endif
                                </label>
                            @endif
                            @include('components.dynamic-field', [
                                'field'      => $field,
                                'name'       => $fName,
                                'value'      => $fValue,
                                'userDeptId' => $userDeptId,
                                'editorRole' => $editorRole,
                            ])
                        </div>
                    @endforeach
                    </x-document-form-fields-grid>
                    @if($editorRole !== 'view_only')
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="btn-primary">{{ __('common.save_fields') }}</button>
                        </div>
                    </form>
                    @endif
                </div>
            @elseif(!empty($instance->payload) && is_array($instance->payload))
                <div class="border-t border-slate-200 dark:border-slate-600 pt-4 space-y-2">
                    @foreach ($instance->payload as $key => $val)
                        <div class="text-sm">
                            <span class="text-slate-500 dark:text-slate-400">{{ $key }}</span>
                            <p class="text-slate-900 dark:text-slate-100 mt-0.5 whitespace-pre-wrap">{{ is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.approval_steps') }}</h3>
                <ol class="space-y-2 text-sm">
                    @foreach ($instance->steps as $step)
                        <li class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 px-3 py-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100">#{{ $step->step_no }} {{ $step->stage_name }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 ml-2">{{ $step->approver_type }}: {{ $step->approver_ref }}</span>
                            <div class="text-xs mt-1">
                                @if ($step->action === 'pending')
                                    <span class="text-amber-600 dark:text-amber-400">{{ __('common.approval_status_pending') }}</span>
                                @elseif ($step->action === 'approved')
                                    <span class="text-slate-600 dark:text-slate-300">{{ __('common.approval_status_approved') }}</span>
                                @elseif ($step->action === 'rejected')
                                    <span class="text-slate-600 dark:text-slate-300">{{ __('common.approval_status_rejected') }}</span>
                                @else
                                    <span class="text-slate-600 dark:text-slate-300">{{ $step->action }}</span>
                                    @if ($step->actor)
                                        · {{ $step->actor->full_name }}
                                    @endif
                                    @if ($step->acted_at)
                                        · {{ $step->acted_at->format('Y-m-d H:i') }}
                                    @endif
                                @endif
                            </div>
                            @if ($step->comment)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $step->comment }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($canAct)
                <div class="card p-5">
                    <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.approval_actions_title') }}</h3>
                    <form method="POST" action="{{ route('approvals.act', $instance) }}" class="space-y-3" novalidate>
                        @csrf
                        <div>
                            <label class="form-label">{{ __('common.approval_comment') }}</label>
                            <textarea name="comment" rows="2" placeholder="{{ __('common.approval_comment_placeholder') }}"
                                      class="form-input mt-1"></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="action" value="approved" class="btn-primary">{{ __('common.approve') }}</button>
                            <button type="submit" name="action" value="rejected" class="btn-danger">{{ __('common.reject') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
