{{--
    Approval inbox section — reused for both zones on /approvals/my.
    Params (via @include):
      $grouped : Collection of ApprovalInstance keyed by document_type
      $mode    : 'pending' (awaiting my action) | 'history' (I already acted)
      $title   : section heading
    Pending mode shows the workflow stage + days-pending + a "take action" button;
    history mode shows the overall status + when I acted + a read-only "view" link.
--}}
@php
    $viewerId = (int) (session('user.id') ?? 0);
@endphp
<section>
    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ $title }}</h3>
    <div class="space-y-6">
        @foreach($grouped as $docType => $group)
            @php
                $docTypeModel = \App\Models\DocumentType::resolveByCode($docType);
            @endphp
            <div>
                <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                    @if ($docTypeModel?->icon)
                        <x-nav-icon :name="$docTypeModel->icon" class="w-5 h-5" />
                    @endif
                    <span>{{ $docTypeModel?->label() ?? str_replace('_', ' ', $docType) }}</span>
                    <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ $group->count() }})</span>
                </h4>
                <x-data-table
                    :columns="$mode === 'pending'
                        ? [
                            ['key' => 'reference_no', 'label' => __('common.reference_no')],
                            ['key' => 'requester', 'label' => __('common.requester')],
                            ['key' => 'stage', 'label' => __('common.workflow_stage')],
                            ['key' => 'pending', 'label' => __('common.days_pending'), 'class' => 'text-right'],
                            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
                        ]
                        : [
                            ['key' => 'reference_no', 'label' => __('common.reference_no')],
                            ['key' => 'requester', 'label' => __('common.requester')],
                            ['key' => 'status', 'label' => __('common.status')],
                            ['key' => 'acted_at', 'label' => __('common.approval_acted_at')],
                            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
                        ]"
                    :rows="$group"
                    :empty-message="__('common.no_pending_approvals')"
                >
                    @foreach($group as $instance)
                        @php
                            $current = $instance->steps->firstWhere('step_no', $instance->current_step_no);
                            $detailUrl = $instance->detailUrl();
                            $daysPending = $instance->created_at ? (int) $instance->created_at->diffInDays(now()) : 0;
                            // history: the step this viewer acted on (for the "acted at" column)
                            $myStep = $mode === 'history'
                                ? $instance->steps->firstWhere('acted_by_user_id', $viewerId)
                                : null;
                            $statusBadge = match($instance->status) {
                                'approved' => 'badge-green',
                                'rejected' => 'badge-red',
                                default    => 'badge-blue',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] whitespace-nowrap">
                                @if($detailUrl)
                                    <a href="{{ $detailUrl }}" class="text-sm font-medium font-mono text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $instance->reference_no ?: ('#' . $instance->id) }}
                                    </a>
                                @else
                                    <span class="text-sm font-medium font-mono text-slate-900 dark:text-slate-100">
                                        {{ $instance->reference_no ?: ('#' . $instance->id) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-sm text-slate-700 dark:text-slate-300">
                                {{ optional($instance->requester)->full_name ?? '—' }}
                                <span class="block text-xs text-slate-400">{{ $instance->created_at?->format('d M Y H:i') }}</span>
                            </td>
                            @if($mode === 'pending')
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                                    <span class="badge-blue text-[11px]">{{ $current?->stage_name ?? __('common.approval_status_pending') }}</span>
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-right whitespace-nowrap">
                                    @if($daysPending >= 3)
                                        <span class="badge-red text-[11px]">{{ $daysPending }}</span>
                                    @elseif($daysPending >= 1)
                                        <span class="badge-yellow text-[11px]">{{ $daysPending }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">{{ $daysPending }}</span>
                                    @endif
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-right whitespace-nowrap">
                                    @if($detailUrl)
                                        <a href="{{ $detailUrl }}" class="btn-primary text-xs">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            {{ __('common.approval_take_action') }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            @else
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)]">
                                    <span class="{{ $statusBadge }} text-[11px]">{{ __('common.approval_status_' . $instance->status) }}</span>
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ optional($myStep?->acted_at)->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-[var(--cell-pad-x)] py-[var(--cell-pad-y)] text-right whitespace-nowrap">
                                    @if($detailUrl)
                                        <a href="{{ $detailUrl }}" class="btn-secondary text-xs">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            {{ __('common.view') }}
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        @endforeach
    </div>
</section>
