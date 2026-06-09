@extends('layouts.app')

@section('title', __('common.add') . ' ' . __('common.org_unit'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.org_units'), 'url' => route('settings.org-units.index')],
        ['label' => __('common.add')],
    ]" />
@endsection

@section('content')
<div>
    <div class="flex items-center justify-between gap-4 mb-6">
        <nav class="text-sm text-slate-500 dark:text-slate-400">
            <span>{{ __('common.settings') }}</span>
            <span class="mx-1">/</span>
            <a href="{{ route('settings.org-units.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">{{ __('common.org_units') }}</a>
        </nav>
        <a href="{{ route('settings.org-units.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert-error mb-4">
            <ul class="text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.org-units.store') }}" novalidate>
        @csrf
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('common.org_unit') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label class="form-label">{{ __('common.name') }} <span class="text-red-500">*</span></label>
                    <input name="name" value="{{ old('name') }}" required maxlength="255"
                           class="form-input @error('name') form-input-error @enderror" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('common.org_type') }} <span class="text-red-500">*</span></label>
                    <select name="type" required class="form-input @error('type') form-input-error @enderror">
                        @foreach (['company','division','department','section','team'] as $t)
                            <option value="{{ $t }}" @selected(old('type', 'department') === $t)>{{ __('common.org_type_' . $t) }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('common.org_parent') }}</label>
                    <select name="parent_id" class="form-input @error('parent_id') form-input-error @enderror">
                        <option value="">— {{ __('common.org_root_node') }} —</option>
                        @foreach ($allUnits as $u)
                            <option value="{{ $u->id }}" @selected(old('parent_id') == $u->id)>
                                {{ $u->name }} ({{ $u->auto_code }})
                            </option>
                        @endforeach
                    </select>
                    @error('parent_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('common.org_head') }}</label>
                    <select name="head_user_id" class="form-input @error('head_user_id') form-input-error @enderror">
                        <option value="">—</option>
                        @foreach ($headCandidates as $u)
                            <option value="{{ $u->id }}" @selected(old('head_user_id') == $u->id)>
                                {{ $u->first_name }} {{ $u->last_name }} ({{ $u->email }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('common.org_head_hint') }}</p>
                    @error('head_user_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                @if ($branches->isNotEmpty())
                <div>
                    <label class="form-label">{{ __('common.branch') }}</label>
                    <select name="branch_id" class="form-input @error('branch_id') form-input-error @enderror">
                        <option value="">—</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                <div>
                    <label class="form-label">{{ __('common.sort_order') }}</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           class="form-input @error('sort_order') form-input-error @enderror" />
                    @error('sort_order')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <x-form.active-toggle name="is_active" :checked="old('is_active', true)" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 pt-2 pb-4">
            <a href="{{ route('settings.org-units.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
@endsection
