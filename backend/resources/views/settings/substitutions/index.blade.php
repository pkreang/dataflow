@extends('layouts.app')

@section('title', __('common.substitutions'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.substitutions')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.substitutions') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.substitution_hint') }}</p>
        </div>
        <a href="{{ route('settings.substitutions.create') }}" class="btn-primary inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('common.add') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
    @endif

    @if ($substitutions->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('common.no_data') }}</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="table-wrapper">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.substitution_from') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.substitution_to') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.date_range') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.reason') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('common.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($substitutions as $sub)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                {{ trim($sub->fromUser->first_name . ' ' . $sub->fromUser->last_name) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-900 dark:text-slate-100">
                                {{ trim($sub->toUser->first_name . ' ' . $sub->toUser->last_name) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">
                                {{ $sub->starts_at->format('d/m/Y') }}
                                @if ($sub->ends_at) — {{ $sub->ends_at->format('d/m/Y') }} @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $sub->reason ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($sub->is_active)
                                    <span class="badge-success text-xs">{{ __('common.active') }}</span>
                                @else
                                    <span class="badge-neutral text-xs">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-row-actions>
                                    <form method="POST" action="{{ route('settings.substitutions.toggle', $sub) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="dropdown-item">
                                            {{ $sub->is_active ? __('common.deactivate') : __('common.activate') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('settings.substitutions.destroy', $sub) }}"
                                          onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="dropdown-item text-red-600 dark:text-red-400">
                                            {{ __('common.delete') }}
                                        </button>
                                    </form>
                                </x-row-actions>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                {{ $substitutions->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
