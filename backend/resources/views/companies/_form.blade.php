@php
    $company = $company ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
    <div>
        <label for="code" class="form-label">
            {{ __('company.company_code') }} <span class="text-red-500">*</span>
        </label>
        <input type="text" name="code" id="code" value="{{ old('code', $company?->code) }}" required maxlength="50"
               class="form-input @error('code') form-input-error @enderror">
        @error('code')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="form-label">
            {{ __('company.company_name') }} <span class="text-red-500">*</span>
        </label>
        <input type="text" name="name" id="name" value="{{ old('name', $company?->name) }}" required maxlength="255"
               class="form-input @error('name') form-input-error @enderror">
        @error('name')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="tax_id" class="form-label">
            {{ __('company.tax_id') }}
        </label>
        <input type="text" name="tax_id" id="tax_id" value="{{ old('tax_id', $company?->tax_id) }}" maxlength="20"
               class="form-input @error('tax_id') form-input-error @enderror">
        @error('tax_id')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="business_type" class="form-label">
            {{ __('company.business_type') }}
        </label>
        <input type="text" name="business_type" id="business_type" value="{{ old('business_type', $company?->business_type) }}" maxlength="100"
               placeholder="{{ __('company.business_type_placeholder') }}"
               class="form-input @error('business_type') form-input-error @enderror">
        @error('business_type')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @include('companies._address_fields', ['prefix' => '', 'model' => $company])

    <div>
        <label for="phone" class="form-label">
            {{ __('company.phone') }}
        </label>
        <input type="text" name="phone" id="phone" value="{{ old('phone', $company?->phone) }}" maxlength="20"
               class="form-input @error('phone') form-input-error @enderror">
        @error('phone')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="form-label">
            {{ __('company.email') }}
        </label>
        <input type="email" name="email" id="email" value="{{ old('email', $company?->email) }}"
               class="form-input @error('email') form-input-error @enderror">
        @error('email')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="fax" class="form-label">
            {{ __('company.fax') }}
        </label>
        <input type="text" name="fax" id="fax" value="{{ old('fax', $company?->fax) }}" maxlength="20"
               class="form-input @error('fax') form-input-error @enderror">
        @error('fax')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="website" class="form-label">
            {{ __('company.website') }}
        </label>
        <input type="text" name="website" id="website" value="{{ old('website', $company?->website) }}" maxlength="255"
               placeholder="https://..."
               class="form-input @error('website') form-input-error @enderror">
        @error('website')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="form-label">
            {{ __('company.description') }}
        </label>
        <textarea name="description" id="description" rows="3" maxlength="1000"
                  class="form-input @error('description') form-input-error @enderror">{{ old('description', $company?->description) }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="logo" class="form-label">
            {{ __('company.logo') }}
        </label>
        @if ($company)
            @php
                $logoPath = $company->logo;
                $logoExists = $logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath);
            @endphp
            <div class="mb-3 flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="shrink-0">
                    @if ($logoExists)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                             alt=""
                             class="w-28 h-28 rounded-lg object-contain border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 p-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">{{ __('company.current_logo') }}</p>
                    @elseif ($logoPath)
                        <div class="w-28 h-28 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center p-2">
                            <p class="text-xs text-center text-amber-800 dark:text-amber-200">{{ __('company.logo_file_missing') }}</p>
                        </div>
                    @else
                        <div class="w-28 h-28 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 flex items-center justify-center bg-slate-50 dark:bg-slate-900/50 p-2">
                            <p class="text-xs text-center text-slate-500 dark:text-slate-400">{{ __('company.no_logo_yet') }}</p>
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0 space-y-1">
                    <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                           class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 dark:file:bg-slate-600 dark:file:text-slate-200 @error('logo') form-input-error @enderror">
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('company.logo_constraints') }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('company.logo_replace_hint') }}</p>
                </div>
            </div>
        @else
            <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                   class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700 dark:file:bg-slate-600 dark:file:text-slate-200 @error('logo') form-input-error @enderror">
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('company.logo_constraints') }}</p>
        @endif
        @error('logo')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <x-form.active-toggle
            name="is_active"
            :checked="old('is_active', $company?->is_active ?? true)"
            :label="__('company.status')" />
    </div>
</div>
