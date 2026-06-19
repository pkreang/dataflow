@extends('layouts.app')

@section('title', __('common.add_user'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.user_and_access'), 'url' => route('users.index')],
        ['label' => __('common.add_user')],
    ]" />
@endsection

@php
    $roleType = old('role_type', 'default');
    $oldPermissions = array_map('intval', (array) old('permissions', []));
@endphp

@section('content')
<div>

    <div class="flex justify-end mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 shrink-0">&larr; {{ __('common.back') }}</a>
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

    <form method="POST" action="{{ route('users.store') }}" id="user-create-form" x-data="{ roleType: '{{ $roleType }}' }" novalidate>
        @csrf
        <input type="hidden" name="role_type" :value="roleType">

        {{-- Section 1: General Info --}}
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('users.general_info') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                {{-- Row 1: First name / Last name --}}
                <div>
                    <label for="first_name" class="form-label">
                        {{ __('common.first_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required maxlength="255"
                           placeholder="{{ __('users.placeholder_first_name') }}"
                           class="form-input @error('first_name') form-input-error @enderror">
                    @error('first_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="form-label">
                        {{ __('common.last_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required maxlength="255"
                           placeholder="{{ __('users.placeholder_last_name') }}"
                           class="form-input @error('last_name') form-input-error @enderror">
                    @error('last_name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Row 2: Email / Department --}}
                <div>
                    <label for="email" class="form-label">
                        {{ __('common.email') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           placeholder="{{ __('users.placeholder_email') }}"
                           class="form-input @error('email') form-input-error @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="department_id" class="form-label">
                        {{ __('common.department') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="department_id" id="department_id" required
                            class="form-input @error('department_id') form-input-error @enderror">
                        <option value="">{{ __('common.choose_department') }}</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="org_unit_id" class="form-label">{{ __('common.org_unit') }}</label>
                    <select name="org_unit_id" id="org_unit_id"
                            class="form-input @error('org_unit_id') form-input-error @enderror">
                        <option value="">—</option>
                        @foreach ($orgUnits as $org)
                            <option value="{{ $org->id }}" @selected(old('org_unit_id') == $org->id)>{{ $org->name }}</option>
                        @endforeach
                    </select>
                    @error('org_unit_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Row 3: Position / Phone --}}
                <div>
                    <label for="position_id" class="form-label">
                        {{ __('common.position') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="position_id" id="position_id" required
                            class="form-input @error('position_id') form-input-error @enderror">
                        <option value="">{{ __('common.choose_position') }}</option>
                        @foreach ($positions as $pos)
                            <option value="{{ $pos->id }}" @selected(old('position_id') == $pos->id)>{{ $pos->name }} ({{ $pos->code }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('users.position_from_master_hint') }}</p>
                    @error('position_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="form-label">
                        {{ __('users.phone') }}
                    </label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" maxlength="50"
                           placeholder="{{ __('users.placeholder_phone') }}"
                           class="form-input @error('phone') form-input-error @enderror">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Row 4: Remark --}}
                <div class="md:col-span-2">
                    <label for="remark" class="form-label">
                        {{ __('users.remark') }}
                    </label>
                    <textarea name="remark" id="remark" rows="1" maxlength="1000"
                              placeholder="{{ __('users.placeholder_remark') }}"
                              class="form-input resize-y @error('remark') form-input-error @enderror">{{ old('remark') }}</textarea>
                    @error('remark')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <x-form.active-toggle name="is_active" :checked="true" />
                </div>
            </div>
        </div>

        {{-- Section 2: Role & Access --}}
        <div class="card p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('common.role_and_access') }}</h3>

            {{-- Toggle tabs --}}
            <div class="inline-flex rounded-lg border border-slate-300 dark:border-slate-600 mb-6">
                <button type="button" @click="roleType = 'default'"
                        :class="roleType === 'default' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-4 py-2 text-sm font-medium rounded-l-lg transition border-slate-300 dark:border-slate-600">
                    {{ __('common.default_role') }}
                </button>
                <button type="button" @click="roleType = 'custom'"
                        :class="roleType === 'custom' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="px-4 py-2 text-sm font-medium rounded-r-lg border-l border-slate-300 dark:border-slate-600 transition">
                    {{ __('common.custom_role') }}
                </button>
            </div>

            {{-- Tab 1: Default role --}}
            <div x-show="roleType === 'default'" x-cloak
                 class="role-panel"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                <label for="role_id" class="form-label">{{ __('common.select_role') }} <span class="text-red-500">*</span></label>
                <select name="role_id" id="role_id" :disabled="roleType !== 'default'"
                        class="form-input max-w-md disabled:opacity-50 disabled:cursor-not-allowed @error('role_id') form-input-error @enderror">
                    <option value="">{{ __('common.choose_role') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                            {{ $role->display_name ?? ucfirst(str_replace('-', ' ', $role->name)) }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tab 2: Custom role (Permission matrix) --}}
            <div x-show="roleType === 'custom'" x-cloak
                 class="role-panel"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100">
                @error('permissions')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @if(empty($permissionMatrix))
                    <p class="text-sm text-amber-600 dark:text-amber-400 py-4">{{ __('users.no_permissions_configured') }}</p>
                @else
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-600 rounded-lg">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="table-header px-6 py-3 text-left w-56">
                                        {{ __('common.module') }}
                                    </th>
                                    @foreach ($permissionActions as $action)
                                        <th class="table-header px-6 py-3 text-center w-28">
                                            {{ $permissionActionLabels[$action] ?? $action }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                                @foreach ($permissionMatrix as $row)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-150">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                            {{ $row['label'] }}
                                        </td>
                                        @foreach ($permissionActions as $action)
                                            <td class="px-6 py-3 text-center">
                                                @if(!empty($row['actions'][$action]))
                                                    <input type="checkbox" name="permissions[]" value="{{ $row['actions'][$action] }}"
                                                           :disabled="roleType !== 'custom'"
                                                           class="permission-cb rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                                           {{ in_array($row['actions'][$action], $oldPermissions) ? 'checked' : '' }}>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-500">&mdash;</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end pt-2 pb-4">
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('users.index') }}" class="btn-secondary">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
