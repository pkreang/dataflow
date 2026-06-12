@extends('layouts.app')

@section('title', __('common.my_submissions'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.forms_index_title'), 'url' => route('forms.index')],
        ['label' => __('common.my_submissions')],
    ]" />
@endsection

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.my_submissions') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.my_submissions_desc') }}</p>
        </div>
        <a href="{{ route('forms.index') }}"
           class="btn-primary">
            {{ __('common.fill_form') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if($submissions->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-slate-500 dark:text-slate-400 text-sm">{{ __('common.no_submissions_yet') }}</p>
        </div>
    @else
        <div class="space-y-8">
            @foreach($submissions as $formName => $group)
                <div>
                    <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-3">
                        {{ $formName }}
                    </h3>
                    <div class="card divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($group as $submission)
                            @php
                                $isDraft = $submission->status === 'draft';
                                $link = $isDraft
                                    ? route('forms.draft.edit', $submission)
                                    : route('forms.submission.show', $submission);
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ $submission->reference_no ?: ('#' . $submission->id) }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ $submission->created_at->format('d M Y H:i') }}
                                    </p>
                                    @if($submission->isOnBehalf())
                                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                                            {{ __('common.submitted_on_behalf_by', [
                                                'creator' => $submission->createdBy?->full_name ?? '—',
                                                'owner' => $submission->user?->full_name ?? '—',
                                            ]) }}
                                        </p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($isDraft)
                                        <span class="badge-yellow">
                                            {{ __('common.draft') }}
                                        </span>
                                    @else
                                        @php $status = $submission->instance?->status ?? 'submitted'; @endphp
                                        <span @class([
                                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                            'badge-blue' => $status === 'pending',
                                            'badge-green' => $status === 'approved',
                                            'badge-red' => $status === 'rejected',
                                        ])>
                                            {{ __('common.approval_status_' . $status) }}
                                        </span>
                                    @endif
                                    <a href="{{ $link }}"
                                       class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ __('common.view') }} &rarr;
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
