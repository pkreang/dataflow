@extends('layouts.app')
@section('title', __('common.purchase_requests'))
@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.purchasing')],
        ['label' => __('common.purchase_requests')],
    ]" />
@endsection
@section('content')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.purchase_requests') }}</h2>
        <a href="{{ route('purchase-requests.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add_purchase_request') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Status filter tabs --}}
    <div class="flex gap-2 mb-4">
        @foreach (['' => __('common.all'), 'pending' => __('common.status_pending'), 'approved' => __('common.status_approved'), 'rejected' => __('common.status_rejected')] as $val => $label)
            <a href="{{ route('purchase-requests.index', $val !== '' ? ['status' => $val] : []) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ ($status ?? '') === $val ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <x-data-table
        :columns="[
            ['key' => 'reference_no', 'label' => __('common.reference_no')],
            ['key' => 'department', 'label' => __('common.department')],
            ['key' => 'status', 'label' => __('common.status')],
            ['key' => 'created_at', 'label' => __('common.created_at')],
        ]"
        :rows="$myInstances"
        :disable-pagination="true"
        :empty-message="__('common.no_purchase_requests')"
        :empty-cta-href="route('purchase-requests.create')"
        :empty-cta-label="__('common.create_purchase_request')"
    >
        @foreach($myInstances as $instance)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="table-primary">
                    <a href="{{ route('purchase-requests.show', $instance) }}"
                       class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        {{ $instance->reference_no ?? '#'.$instance->id }}
                    </a>
                </td>
                <td class="table-sub">{{ $instance->department?->name ?? '—' }}</td>
                <td class="px-4 py-2">
                    @php $s = $instance->status; @endphp
                    @if($s === 'approved')
                        <span class="badge-green">{{ __('common.approval_status_' . $s) }}</span>
                    @elseif($s === 'rejected')
                        <span class="badge-red">{{ __('common.approval_status_' . $s) }}</span>
                    @else
                        <span class="badge-yellow">{{ __('common.approval_status_' . $s) }}</span>
                    @endif
                </td>
                <td class="table-sub">{{ $instance->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$myInstances" :perPage="$perPage" id="purchase-requests-pagination" />
@endsection
