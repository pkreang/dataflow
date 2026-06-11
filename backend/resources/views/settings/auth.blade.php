@extends('layouts.app')

@section('title', __('auth.settings_title'))

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => __('common.settings')],
        ['label' => __('common.authentication_sso')],
    ]" />
@endsection

@section('content')
    <div class="w-full">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ __('auth.settings_title') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('auth.settings_subtitle') }}</p>
        </div>

        <div class="alert-info mb-6">
            <h3 class="text-sm font-semibold mb-2">{{ __('auth.settings_where_title') }}</h3>
            <p class="text-sm">{{ __('auth.settings_where_body') }}</p>
        </div>

        @if (session('success'))
            <div class="alert-success mb-4">
                <p class="text-sm">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->has('auth'))
            <div class="alert-error mb-4">
                <p class="text-sm">{{ $errors->first('auth') }}</p>
            </div>
        @endif

        @if ($errors->any() && ! $errors->has('auth'))
            <div class="alert-error mb-4">
                <ul class="text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.auth.save') }}"
              x-data="{
                  entra: {{ old('auth_entra_enabled', $settings['auth_entra_enabled'] ?? '0') == '1' ? 'true' : 'false' }},
                  ldap: {{ old('auth_ldap_enabled', $settings['auth_ldap_enabled'] ?? '0') == '1' ? 'true' : 'false' }}
              }" novalidate>
            @csrf

            <div class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('auth.settings_methods') }}</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="auth_local_enabled" value="0">
                        <input type="checkbox" name="auth_local_enabled" value="1"
                               {{ old('auth_local_enabled', $settings['auth_local_enabled'] ?? '1') == '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('auth.settings_local') }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer ml-6">
                        <input type="hidden" name="auth_local_super_admin_only" value="0">
                        <input type="checkbox" name="auth_local_super_admin_only" value="1"
                               {{ old('auth_local_super_admin_only', $settings['auth_local_super_admin_only'] ?? '0') == '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('auth.settings_local_super_admin_only') }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="auth_entra_enabled" value="0">
                        <input type="checkbox" name="auth_entra_enabled" value="1"
                               x-model="entra"
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('auth.settings_entra') }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="auth_ldap_enabled" value="0">
                        <input type="checkbox" name="auth_ldap_enabled" value="1"
                               x-model="ldap"
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('auth.settings_ldap') }}</span>
                    </label>
                </div>
            </div>

            <div x-show="entra || ldap" x-transition class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ __('auth.settings_password_help_section') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ __('auth.settings_password_help_hint') }}</p>
                <div>
                    <label for="auth_password_help_url" class="form-label">{{ __('auth.settings_password_help_url_label') }}</label>
                    <input type="url" name="auth_password_help_url" id="auth_password_help_url"
                           value="{{ old('auth_password_help_url', $settings['auth_password_help_url'] ?? '') }}"
                           placeholder="https://passwordreset.microsoftonline.com/"
                           class="form-input w-full max-w-2xl mt-1">
                    @error('auth_password_help_url')
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div x-show="entra || ldap" x-transition class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-4">{{ __('auth.settings_jit_role') }}</h3>
                <div>
                    <label for="auth_default_role" class="form-label">{{ __('auth.settings_default_role') }}</label>
                    <select name="auth_default_role" id="auth_default_role"
                            class="form-input w-full max-w-md mt-1">
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}"
                                {{ old('auth_default_role', $settings['auth_default_role'] ?? 'employee') === $role->name ? 'selected' : '' }}>
                                {{ $role->display_name ?? $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div x-show="entra || ldap" x-transition class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ __('auth.settings_group_role_map_section') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('auth.settings_group_role_map_hint') }}</p>
                <pre class="text-xs text-slate-500 dark:text-slate-400 mb-3 p-3 bg-white/50 dark:bg-slate-900/40 rounded-lg border border-slate-200 dark:border-slate-600 overflow-x-auto font-mono">{{ __('auth.settings_group_role_map_example') }}</pre>
                <div>
                    <label for="auth_directory_group_role_map" class="form-label">{{ __('auth.settings_group_role_map_label') }}</label>
                    <textarea name="auth_directory_group_role_map" id="auth_directory_group_role_map" rows="12"
                              class="form-input w-full max-w-4xl mt-1 font-mono"
                              spellcheck="false">{{ old('auth_directory_group_role_map', $settings['auth_directory_group_role_map'] ?? '[]') }}</textarea>
                    @error('auth_directory_group_role_map')
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div x-show="entra" x-transition class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ __('auth.settings_entra_section') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    {{ __('auth.settings_entra_secret_hint') }}
                    @if ($entraEnvOk)
                        <span class="text-green-600 dark:text-green-400 font-medium">{{ __('auth.env_configured') }}</span>
                    @else
                        <span class="text-amber-600 dark:text-amber-400 font-medium">{{ __('auth.env_missing_entra') }}</span>
                    @endif
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="entra_tenant_id" class="form-label">{{ __('auth.settings_entra_tenant') }}</label>
                        <input type="text" name="entra_tenant_id" id="entra_tenant_id"
                               value="{{ old('entra_tenant_id', $settings['entra_tenant_id'] ?? '') }}"
                               class="form-input w-full mt-1">
                    </div>
                    <div>
                        <label for="entra_client_id" class="form-label">{{ __('auth.settings_entra_client_id') }}</label>
                        <input type="text" name="entra_client_id" id="entra_client_id"
                               value="{{ old('entra_client_id', $settings['entra_client_id'] ?? '') }}"
                               class="form-input w-full mt-1">
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">{{ __('auth.settings_entra_redirect', ['url' => route('auth.entra.callback', [], true)]) }}</p>
            </div>

            <div x-show="ldap" x-transition class="card p-6 mb-6">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200 mb-2">{{ __('auth.settings_ldap_section') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    {{ __('auth.settings_ldap_secret_hint') }}
                    @if ($ldapEnvOk)
                        <span class="text-green-600 dark:text-green-400 font-medium">{{ __('auth.env_configured') }}</span>
                    @else
                        <span class="text-amber-600 dark:text-amber-400 font-medium">{{ __('auth.env_missing_ldap') }}</span>
                    @endif
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="ldap_host" class="form-label">{{ __('auth.settings_ldap_host') }}</label>
                        <input type="text" name="ldap_host" id="ldap_host"
                               value="{{ old('ldap_host', $settings['ldap_host'] ?? '') }}"
                               class="form-input w-full mt-1">
                    </div>
                    <div>
                        <label for="ldap_port" class="form-label">{{ __('auth.settings_ldap_port') }}</label>
                        <input type="number" name="ldap_port" id="ldap_port"
                               value="{{ old('ldap_port', $settings['ldap_port'] ?? 389) }}"
                               class="form-input w-full max-w-xs mt-1">
                    </div>
                    <div class="md:col-span-2">
                        <label for="ldap_base_dn" class="form-label">{{ __('auth.settings_ldap_base_dn') }}</label>
                        <input type="text" name="ldap_base_dn" id="ldap_base_dn"
                               value="{{ old('ldap_base_dn', $settings['ldap_base_dn'] ?? '') }}"
                               class="form-input w-full mt-1">
                    </div>
                    <div class="md:col-span-2">
                        <label for="ldap_bind_dn" class="form-label">{{ __('auth.settings_ldap_bind_dn') }}</label>
                        <input type="text" name="ldap_bind_dn" id="ldap_bind_dn"
                               value="{{ old('ldap_bind_dn', $settings['ldap_bind_dn'] ?? '') }}"
                               class="form-input w-full mt-1">
                    </div>
                    <div class="md:col-span-2">
                        <label for="ldap_user_filter" class="form-label">{{ __('auth.settings_ldap_filter') }}</label>
                        <input type="text" name="ldap_user_filter" id="ldap_user_filter"
                               value="{{ old('ldap_user_filter', $settings['ldap_user_filter'] ?? '(mail=%s)') }}"
                               class="form-input w-full mt-1 font-mono text-xs">
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer md:col-span-2">
                        <input type="hidden" name="ldap_use_tls" value="0">
                        <input type="checkbox" name="ldap_use_tls" value="1"
                               {{ old('ldap_use_tls', $settings['ldap_use_tls'] ?? '0') == '1' ? 'checked' : '' }}
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('auth.settings_ldap_tls') }}</span>
                    </label>
                    <div class="md:col-span-2 pt-2 border-t border-slate-200 dark:border-slate-600 mt-2">
                        <label for="ldap_user_create_validation" class="form-label">{{ __('auth.settings_ldap_user_create_validation') }}</label>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('auth.settings_ldap_user_create_validation_hint') }}</p>
                        <select name="ldap_user_create_validation" id="ldap_user_create_validation"
                                class="form-input w-full max-w-md mt-1">
                            <option value="disabled" {{ old('ldap_user_create_validation', $settings['ldap_user_create_validation'] ?? 'disabled') === 'disabled' ? 'selected' : '' }}>
                                {{ __('auth.settings_ldap_user_create_validation_disabled') }}
                            </option>
                            <option value="required" {{ old('ldap_user_create_validation', $settings['ldap_user_create_validation'] ?? 'disabled') === 'required' ? 'selected' : '' }}>
                                {{ __('auth.settings_ldap_user_create_validation_required') }}
                            </option>
                        </select>
                        @error('ldap_user_create_validation')
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="btn-primary">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
@endsection
