@extends($layout ?? 'layouts.app')

@section('title', __('common.evaluation_report_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.reports'), 'url' => route('reports.index')],
        ['label' => __('common.evaluation_report_title')],
    ]" />
@endsection

@section('content')
<div>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.evaluation_report_title') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.evaluation_report_desc') }}</p>
    </div>

    {{-- Summary cards 4-col --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.evaluation_total') }}</p>
            <p class="text-3xl font-bold text-slate-900 dark:text-slate-100 mt-1">{{ number_format($totalCount) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.evaluation_avg_rating') }}</p>
            <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $overallAvg }} <span class="text-lg text-slate-400">/ 5</span></p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.evaluation_response_rate') }}</p>
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $responseRate }}%</p>
            <p class="text-xs text-slate-400 mt-1">{{ $totalCount }} / {{ $eligibleParents }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.evaluation_form_count') }}</p>
            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ $perForm->count() }}</p>
        </div>
    </div>

    {{-- 2-col: rating distribution + per-form bars --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.evaluation_rating_distribution') }}</h3>
            @php $maxCount = max(array_values($distribution)) ?: 1; @endphp
            <div class="space-y-2">
                @foreach([5,4,3,2,1] as $r)
                    @php
                        $count = $distribution[$r] ?? 0;
                        $width = $count > 0 ? ($count / $maxCount) * 100 : 0;
                        $pct = $totalCount > 0 ? round(($count / $totalCount) * 100) : 0;
                        $tone = ['5'=>'emerald','4'=>'green','3'=>'blue','2'=>'amber','1'=>'red'][(string) $r];
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-12 shrink-0 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ str_repeat('⭐', $r) }}</div>
                        <div class="flex-1 h-6 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full rounded-full bg-{{ $tone }}-500 transition-all"
                                 style="width: {{ $width }}%"></div>
                        </div>
                        <div class="w-20 shrink-0 text-right">
                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $count }}</span>
                            <span class="text-xs text-slate-400">({{ $pct }}%)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.evaluation_per_form') }}</h3>
            @if($perForm->isEmpty())
                <p class="text-sm text-slate-400 text-center py-8">{{ __('common.no_data') }}</p>
            @else
                <div class="space-y-3">
                    @foreach($perForm as $f)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 truncate">{{ $f['name'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 shrink-0 ml-2">
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $f['avg'] }}</span>
                                    <span class="text-slate-400">/ 5</span>
                                    · {{ $f['count'] }} ครั้ง
                                </p>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ ($f['avg'] / 5) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Recent evaluations list --}}
    <div class="card p-5">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{{ __('common.evaluation_recent') }}</h3>
        @if($recent->isEmpty())
            <p class="text-sm text-slate-400 text-center py-8">{{ __('common.no_data') }}</p>
        @else
            <div class="space-y-3">
                @foreach($recent as $eval)
                    @php
                        $rating = $extractRating($eval->payload['overall_rating'] ?? null);
                        $comment = $eval->payload['comment'] ?? '';
                        $parent = $eval->originalSubmission;
                    @endphp
                    <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                        <div class="text-2xl shrink-0">{{ $rating ? str_repeat('⭐', $rating) : '—' }}</div>
                        <div class="flex-1 min-w-0">
                            @if($parent)
                                <p class="text-xs font-mono text-slate-500 dark:text-slate-400">
                                    {{ $parent->reference_no ?: '#'.$parent->id }} · {{ $parent->form?->name }}
                                </p>
                            @endif
                            @if($comment)
                                <p class="text-sm text-slate-700 dark:text-slate-300 mt-0.5">{{ $comment }}</p>
                            @endif
                            <p class="text-xs text-slate-400 mt-1">
                                {{ $eval->user?->first_name }} {{ $eval->user?->last_name }}
                                · {{ $eval->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
