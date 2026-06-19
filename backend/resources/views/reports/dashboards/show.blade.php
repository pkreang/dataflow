@extends('layouts.app')

@section('title', $dashboard->name)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.reports'), 'url' => route('reports.index')],
        ['label' => $dashboard->name],
    ]" />
@endsection

@push('scripts')
<meta name="api-token" content="{{ $apiToken ?? '' }}">
@endpush

@section('content')
<div class="mb-6">
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ $dashboard->name }}</h2>
    @if($dashboard->description)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $dashboard->description }}</p>
    @endif
</div>

@include('_partials.dashboard-widget-grid', ['dashboard' => $dashboard, 'orgUnits' => $orgUnits])

@endsection
