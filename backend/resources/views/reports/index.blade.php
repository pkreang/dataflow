@extends($layout ?? 'layouts.app')

@section('title', __('common.reports'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.reports')],
    ]" />
@endsection

@section('content')
<div>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.reports') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.reports_desc') }}</p>
    </div>

    @if($dashboards->isEmpty())
        <div class="card p-12 text-center">
            <svg class="w-10 h-10 mx-auto text-slate-400 dark:text-slate-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_dashboards') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($dashboards as $dashboard)
                <a href="{{ route('reports.dashboards.show', $dashboard) }}"
                   class="group block card p-5 hover:border-blue-400 dark:hover:border-blue-500 hover:shadow-md transition-all duration-150">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center shrink-0 group-hover:bg-blue-600 transition-colors">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                {{ $dashboard->name }}
                            </h3>
                            @if($dashboard->description)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">{{ $dashboard->description }}</p>
                            @endif
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">
                                {{ $dashboard->widgets_count }} {{ __('common.dashboard_widgets') }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-blue-400 transition-colors shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
