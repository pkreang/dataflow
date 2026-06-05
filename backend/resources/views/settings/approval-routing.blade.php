@extends('layouts.app')

@section('title', __('common.approval_routing'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.workflow'), 'url' => route('settings.workflow.index')],
        ['label' => __('common.approval_routing')],
    ]" />
@endsection

@section('content')
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('common.approval_routing') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('common.approval_routing_subtitle') }}</p>
            </div>
            <a href="{{ route('settings.workflow.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500">&larr; {{ __('common.back') }}</a>
        </div>

        @if (session('success'))
            <div class="alert-success mb-4">
                <p class="text-sm">{{ session('success') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.approval-routing.save') }}" novalidate>
            @csrf

            <div class="table-wrapper">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="table-header">{{ __('common.document_type') }}</th>
                            <th class="table-header">{{ __('common.routing_mode') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($documentTypes as $docType)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-5 py-3.5 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $docType->label() }}</td>
                                <td class="px-5 py-3">
                                    <select name="routing_modes[{{ $docType->code }}]"
                                            class="form-input w-full max-w-xs">
                                        <option value="hybrid" @selected(old("routing_modes.{$docType->code}", $docType->routing_mode) === 'hybrid')>
                                            {{ __('common.routing_mode_by_department') }}
                                        </option>
                                        <option value="organization_wide" @selected(old("routing_modes.{$docType->code}", $docType->routing_mode) === 'organization_wide')>
                                            {{ __('common.routing_mode_org_wide') }}
                                        </option>
                                    </select>
                                    @error("routing_modes.{$docType->code}")
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Master toggle: let requesters pick the approver for stages an admin
                 marks as "requester picks" in the workflow editor. --}}
            <div class="mt-5 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="allow_requester_pick" value="1"
                           @checked(old('allow_requester_pick', $allowRequesterPick ?? false)) class="mt-1">
                    <span>
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ __('common.approval_allow_requester_pick') }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('common.approval_allow_requester_pick_desc') }}</span>
                    </span>
                </label>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection
