@extends('layouts.app')

@section('title', __('common.org_units'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.org_units')],
    ]" />
@endsection

@section('content')
<div x-data="orgTree()" x-init="init()">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.org_unit_list') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.org_unit_branch_hint') }}</p>
        </div>
        <a href="{{ route('settings.org-units.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add') }} {{ __('common.org_unit') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="alert-error mb-4"><p class="text-sm">{{ session('error') }}</p></div>
    @endif

    @if ($roots->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ __('common.no_data') }}</p>
            <a href="{{ route('settings.org-units.create') }}" class="btn-primary inline-flex items-center text-sm">
                {{ __('common.add') }} {{ __('common.org_unit') }}
            </a>
        </div>
    @else
        <div class="card">
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($roots as $root)
                    @include('settings.org-units._node', ['unit' => $root, 'depth' => 0])
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function orgTree() {
    return {
        collapsed: {},
        init() {},
        toggle(id) {
            this.collapsed[id] = !this.collapsed[id];
        },
        isCollapsed(id) {
            return !!this.collapsed[id];
        }
    };
}
</script>
@endpush
@endsection
