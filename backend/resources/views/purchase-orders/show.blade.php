@extends('layouts.app')
@section('title', __('common.purchase_order') . ' ' . ($instance->reference_no ?? '#'.$instance->id))
@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.purchasing')],
        ['label' => __('common.purchase_orders'), 'url' => route('purchase-orders.index')],
        ['label' => $instance->reference_no ?? '#'.$instance->id],
    ]" />
@endsection
@section('content')
    <div class="mb-6 flex items-start justify-between">
        <div>
            <a href="{{ route('purchase-orders.index') }}" class="text-sm text-blue-600 hover:text-blue-700">&larr; {{ __('common.back') }}</a>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2">
                {{ __('common.purchase_order') }}: {{ $instance->reference_no ?? '#'.$instance->id }}
            </h2>
            @if($sourcePr)
                <p class="text-sm text-slate-500 mt-1">
                    {{ __('common.pr_reference') }}:
                    <a href="{{ route('purchase-requests.show', $sourcePr) }}" class="text-blue-600 hover:underline">
                        {{ $sourcePr->reference_no ?? 'PR#'.$sourcePr->id }}
                    </a>
                </p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @php $s = $instance->status; @endphp
            @if($s === 'approved')
                <span class="badge-green">{{ __('common.approval_status_' . $s) }}</span>
            @elseif($s === 'rejected')
                <span class="badge-red">{{ __('common.approval_status_' . $s) }}</span>
            @else
                <span class="badge-yellow">{{ __('common.approval_status_' . $s) }}</span>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: Document form fields --}}
        <div class="card p-5">
            @include('partials.company-header', ['company' => $company ?? null, 'branch' => $branch ?? null])

            <div class="space-y-3">
                <div class="flex justify-between gap-4 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.reference_no') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100 font-medium">{{ $instance->reference_no ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.user') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $instance->requester?->full_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('common.workflow_name') }}</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $instance->workflow?->name ?? '—' }}</dd>
                </div>
            </div>

            @if($formFields->count())
                <div class="border-t border-slate-200 dark:border-slate-600 mt-4 pt-4">
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
                <div class="border-t border-slate-200 dark:border-slate-600 mt-4 pt-4 space-y-2">
                    @foreach ($instance->payload as $key => $val)
                        @if(! in_array($key, ['amount', 'purchase_request_id', 'parent_reference'], true))
                            <div class="text-sm">
                                <span class="text-slate-500 dark:text-slate-400">{{ $key }}</span>
                                <p class="text-slate-900 dark:text-slate-100 mt-0.5">{{ is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE) }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right: Approval steps --}}
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
                                    <span class="text-green-600 dark:text-green-400">{{ __('common.approval_status_approved') }}</span>
                                @elseif ($step->action === 'rejected')
                                    <span class="text-red-600 dark:text-red-400">{{ __('common.approval_status_rejected') }}</span>
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

    {{-- Line items --}}
    <div class="mt-6 table-wrapper">
        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('common.line_items') }}</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="table-header text-left">#</th>
                    <th class="table-header text-left">{{ __('common.item_name') }}</th>
                    <th class="table-header text-right">{{ __('common.qty') }}</th>
                    <th class="table-header text-left">{{ __('common.unit_label') }}</th>
                    <th class="table-header text-right">{{ __('common.unit_price') }}</th>
                    <th class="table-header text-right">{{ __('common.total_price') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($lineItems as $i => $item)
                    <tr>
                        <td class="px-4 py-2 text-slate-500">{{ $i + 1 }}</td>
                        <td class="px-4 py-2">
                            {{ $item->item_name }}
                            @if($item->notes)
                                <span class="text-xs text-slate-400 block">{{ $item->notes }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($item->qty, 2) }}</td>
                        <td class="px-4 py-2">{{ $item->unit }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 dark:bg-slate-800/60 border-t-2 border-slate-300 dark:border-slate-600">
                <tr>
                    <td colspan="5" class="px-4 py-2 text-right font-semibold text-slate-700 dark:text-slate-300">{{ __('common.total_price') }}</td>
                    <td class="px-4 py-2 text-right font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format($lineItems->sum('total_price'), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection
