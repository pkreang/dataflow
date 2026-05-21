@php
    /** @var \App\Models\Company $company */
@endphp

<div class="mt-10 pt-8 border-t border-slate-200 dark:border-slate-700">
    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-1">{{ __('company.branches_section') }}</h3>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ __('company.branches_section_hint') }}</p>

    @if ($company->branches->isEmpty())
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">{{ __('company.branches_empty') }}</p>
    @else
        <div class="space-y-6 mb-8">
            @foreach ($company->branches as $branch)
                <div class="card p-4">
                    <form method="POST" action="{{ route('companies.branches.update', [$company, $branch]) }}" class="space-y-3" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('company.branch_code') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="code" value="{{ $branch->code }}" required maxlength="50"
                                       class="form-input">
                            </div>
                            <div>
                                <label class="form-label">{{ __('company.branch_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="name" value="{{ $branch->name }}" required maxlength="255"
                                       class="form-input">
                            </div>
                            @include('companies._address_fields', ['prefix' => '', 'model' => $branch])
                            <div>
                                <label class="form-label">{{ __('company.branch_phone') }}</label>
                                <input type="text" name="phone" value="{{ $branch->phone }}" maxlength="20"
                                       class="form-input">
                            </div>
                            <div class="flex items-end">
                                <x-form.active-toggle
                                    name="is_active"
                                    :checked="$branch->is_active"
                                    :label="__('company.branch_active')"
                                    label-class="form-label" />
                            </div>
                        </div>
                        @can('manage profile')
                            <div class="flex flex-wrap gap-2 pt-2">
                                <button type="submit" class="btn-primary text-xs px-3 py-1.5">{{ __('common.save') }}</button>
                            </div>
                        @endcan
                    </form>
                    @can('manage profile')
                        <form method="POST" action="{{ route('companies.branches.destroy', [$company, $branch]) }}" class="mt-2"
                              onsubmit="return confirm(@json(__('company.branch_delete_confirm')));" novalidate>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">{{ __('company.branch_delete') }}</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @can('manage profile')
        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3">{{ __('company.add_branch') }}</h4>
        <form method="POST" action="{{ route('companies.branches.store', $company) }}" class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-4 space-y-3 bg-slate-50/80 dark:bg-slate-900/20" novalidate>
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">{{ __('company.branch_code') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="branch_code" value="{{ old('branch_code') }}" required maxlength="50"
                           class="form-input">
                </div>
                <div>
                    <label class="form-label">{{ __('company.branch_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="branch_name" value="{{ old('branch_name') }}" required maxlength="255"
                           class="form-input">
                </div>
                @include('companies._address_fields', ['prefix' => 'branch_', 'model' => null])
                <div>
                    <label class="form-label">{{ __('company.branch_phone') }}</label>
                    <input type="text" name="branch_phone" value="{{ old('branch_phone') }}" maxlength="20"
                           class="form-input">
                </div>
                <div class="flex items-end">
                    <x-form.active-toggle
                        name="branch_is_active"
                        :checked="old('branch_is_active', '1') === '1'"
                        :label="__('company.branch_active')"
                        label-class="form-label" />
                </div>
            </div>
            <button type="submit" class="btn-secondary">
                {{ __('company.add_branch') }}
            </button>
        </form>
    @endcan
</div>
