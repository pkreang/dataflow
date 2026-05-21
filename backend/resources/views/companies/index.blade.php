@extends('layouts.app')

@section('title', __('company.companies'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('company.companies')],
    ]" />
@endsection

@section('content')
<div>
    @if ($canCreateMore)
    <div class="flex items-center justify-between mb-2">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('company.all_companies') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ trans_choice('company.companies_total', $companies->total(), ['count' => $companies->total()]) }}
            </p>
        </div>
        @can('manage profile')
            <div class="flex items-center gap-2">
                <a href="{{ route('companies.create') }}" class="btn-primary inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('company.add_company') }}
                </a>
            </div>
        @endcan
    </div>
    @endif

    @if ($canCreateMore)
    {{-- Search (multi mode only) --}}
    <x-filter-bar :action="route('companies.index')">
        <x-search-bar :placeholder="__('company.search_placeholder')" />
    </x-filter-bar>
    @endif

    @if (session('error'))
        <div class="alert-error mb-4">
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    @if (session('success'))
        <div class="alert-success mb-4">
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <x-data-table
        :columns="[
            ['key' => 'code', 'label' => __('company.company_code')],
            ['key' => 'company', 'label' => __('company.company')],
            ['key' => 'email', 'label' => __('company.email')],
            ['key' => 'phone', 'label' => __('company.phone')],
            ['key' => 'status', 'label' => __('company.status')],
            ['key' => 'actions', 'label' => __('common.actions'), 'class' => 'text-right'],
        ]"
        :rows="$companies"
        :disable-pagination="true"
        :empty-message="__('company.no_companies_found')"
        :empty-cta-href="auth()->user()?->can('manage profile') ? route('companies.create') : null"
        :empty-cta-label="auth()->user()?->can('manage profile') ? __('company.add_company') : null"
    >
        @foreach ($companies as $company)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                <td class="px-4 py-2 align-top text-sm font-mono text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ $company->code }}</td>
                <td class="px-4 py-2 align-top whitespace-nowrap">
                    <div class="flex items-start gap-2.5">
                        @if ($company->logo)
                            <img src="{{ asset('storage/' . $company->logo) }}" alt="" class="w-8 h-8 rounded-full object-cover shrink-0 ring-2 ring-slate-200 dark:ring-slate-600">
                        @else
                            @php
                                $logoColors = [
                                    'bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500',
                                    'bg-rose-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-pink-500',
                                ];
                                $ci = abs(crc32($company->name)) % count($logoColors);
                                $logoBg = $logoColors[$ci];
                            @endphp
                            <div class="w-8 h-8 rounded-full {{ $logoBg }} flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-white opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                        @endif
                        <div class="min-w-0 pt-1 leading-tight">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate leading-snug">{{ $company->name }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-2 align-top whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                    {{ $company->email ?? '—' }}
                </td>
                <td class="px-4 py-2 align-top whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                    {{ $company->phone ?? '—' }}
                </td>
                <td class="px-4 py-2 align-top whitespace-nowrap">
                    @if ($company->is_active)
                        <span class="badge-green">{{ __('common.active') }}</span>
                    @else
                        <span class="badge-red">{{ __('common.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 align-top whitespace-nowrap text-right">
                    @can('manage profile')
                        @php
                            $rowActions = [
                                ['label' => __('common.edit'), 'href' => route('companies.edit', $company), 'icon' => 'edit'],
                            ];
                            if (($company->branches_count ?? 0) === 0) {
                                $rowActions[] = [
                                    'label' => __('common.delete'),
                                    'method' => 'DELETE',
                                    'action' => route('companies.destroy', $company),
                                    'icon' => 'delete',
                                    'confirm' => __('common.are_you_sure') . ' ' . __('company.delete_company') . '?',
                                    'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
                                ];
                            }
                        @endphp
                        <x-row-actions :items="$rowActions" />
                        @if (($company->branches_count ?? 0) > 0)
                            <span class="sr-only">{{ __('company.cannot_delete_has_branches') }}</span>
                        @endif
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-per-page-footer :paginator="$companies" :perPage="$perPage" id="companies-pagination" />
</div>
@endsection
