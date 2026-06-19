@extends('layouts.app')

@section('title', __('common.spare_parts_requisition_detail'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.spare_parts_requisition'), 'url' => route('spare-parts.requisition.index')],
        ['label' => __('common.spare_parts_requisition_detail')],
    ]" />
@endsection

@section('content')
    <div class="mb-6">
        <a href="{{ route('spare-parts.requisition.index') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">{{ __('common.spare_parts_requisition_detail') }}</h2>
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

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @include('repair-requests._company_header', ['company' => $company ?? null, 'branch' => $branch ?? null])

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: Summary + Line Items --}}
        <div class="space-y-6">
            <div class="card p-5 space-y-4">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('common.payload_summary') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('common.reference_no') }}</dt>
                        <dd class="text-slate-900 dark:text-slate-100 font-medium">{{ $instance->reference_no ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('common.department') }}</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ $instance->department?->name ?? '—' }}</dd>
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
                                    <label class="form-label mb-1">
                                        {{ $field->label }}
                                        @if($field->is_required) <span class="text-red-500">*</span> @endif
                                    </label>
                                @endif
                                @include('components.dynamic-field', [
                                    'field'      => $field,
                                    'name'       => $fName,
                                    'value'      => $fValue,
                                    'userDeptId' => $userDeptId,
                                    'userOrgUnitId' => $userOrgUnitId ?? null,
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
                            @if($key !== 'amount')
                                <div class="text-sm">
                                    <span class="text-slate-500 dark:text-slate-400">{{ $key }}</span>
                                    <p class="text-slate-900 dark:text-slate-100 mt-0.5">{{ is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE) }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Line Items --}}
            <div class="table-wrapper p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.spare_parts_items') }}</h3>
                @if($canIssue)
                    <form method="POST" action="{{ route('spare-parts.requisition.issue', $instance) }}" novalidate>
                        @csrf
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="table-header">{{ __('common.spare_part') }}</th>
                                <th class="table-header text-right">{{ __('common.requested') }}</th>
                                <th class="table-header text-right">{{ __('common.issued') }}</th>
                                <th class="table-header text-right">{{ __('common.unit_cost') }}</th>
                                <th class="table-header text-right">{{ __('common.subtotal') }}</th>
                                @if($canIssue)
                                    <th class="table-header text-right">{{ __('common.issue_qty') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @php $grandTotal = 0; @endphp
                            @foreach($lineItems as $li)
                                @php $grandTotal += $li->quantity_requested * $li->unit_cost; @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-900 dark:text-slate-100">
                                    <td class="px-2 py-2">
                                        <span class="font-medium">{{ $li->sparePart?->code }}</span>
                                        <span class="text-slate-500 dark:text-slate-400 ml-1">{{ $li->sparePart?->name }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-right">{{ number_format($li->quantity_requested, 0) }}</td>
                                    <td class="px-2 py-2 text-right {{ $li->quantity_issued >= $li->quantity_requested ? 'text-green-600 dark:text-green-400' : '' }}">
                                        {{ number_format($li->quantity_issued, 0) }}
                                    </td>
                                    <td class="px-2 py-2 text-right">{{ number_format($li->unit_cost, 2) }}</td>
                                    <td class="px-2 py-2 text-right">{{ number_format($li->quantity_requested * $li->unit_cost, 2) }}</td>
                                    @if($canIssue)
                                        <td class="px-2 py-2 text-right">
                                            @if($li->quantity_issued < $li->quantity_requested)
                                                <input type="hidden" name="issue[{{ $loop->index }}][item_id]" value="{{ $li->id }}">
                                                <input type="number" step="1" min="0"
                                                       max="{{ $li->quantity_requested - $li->quantity_issued }}"
                                                       value="{{ $li->quantity_requested - $li->quantity_issued }}"
                                                       name="issue[{{ $loop->index }}][quantity]"
                                                       class="form-input w-20 text-right text-sm">
                                            @else
                                                <span class="text-green-600 dark:text-green-400 text-xs">{{ __('common.issued_complete') }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold text-slate-900 dark:text-slate-100 border-t border-slate-300 dark:border-slate-600">
                                <td class="px-2 py-2" colspan="{{ $canIssue ? 4 : 4 }}">{{ __('common.total') }}</td>
                                <td class="px-2 py-2 text-right">{{ number_format($grandTotal, 2) }} {{ __('common.baht') }}</td>
                                @if($canIssue)
                                    <td></td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if($canIssue)
                    <button class="btn-primary mt-3">{{ __('common.issue_items') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Right: Approval Steps --}}
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
                <x-approval-action :instance="$instance" />
            @endif
        </div>
    </div>
@endsection
