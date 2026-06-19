@extends('layouts.app')

@section('title', __('common.dashboard'))

@push('scripts')
<meta name="api-token" content="{{ $apiToken ?? '' }}">
@endpush

@section('content')
    {{-- Page header — dashboard title + inline picker for changing the home dashboard. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="min-w-0">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                {{ $dashboard->name }}
            </h2>
            @if($dashboard->description)
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $dashboard->description }}</p>
            @endif
        </div>
        @if($availableDashboards->count() > 1)
            <form method="POST" action="{{ route('profile.home-dashboard.update') }}"
                  class="flex items-center gap-2 shrink-0">
                @csrf
                @method('PATCH')
                <label for="home-dashboard-picker" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('common.change_home_dashboard') }}
                </label>
                <select name="home_dashboard_id"
                        id="home-dashboard-picker"
                        onchange="this.form.submit()"
                        class="form-input py-1.5 text-sm">
                    @foreach($availableDashboards as $option)
                        <option value="{{ $option->id }}"
                            @selected($dashboard->id === $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    @include('_partials.dashboard-widget-grid', ['dashboard' => $dashboard, 'orgUnits' => $orgUnits])
@endsection
