@extends('layouts.app')

@section('title', __('common.lookups'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.lookups'), 'url' => route('settings.lookups.index')],
        ['label' => __('common.add')],
    ]" />
@endsection

@section('content')
<div>
    <a href="{{ route('settings.lookups.index') }}" class="text-sm text-blue-600 hover:underline">&larr; {{ __('common.lookups') }}</a>
    <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mt-2 mb-4">{{ __('common.add') }}</h2>

    <form method="POST" action="{{ route('settings.lookups.store') }}">
        @csrf
        @include('settings.lookups._form', ['lookup' => null])
    </form>
</div>
@endsection
