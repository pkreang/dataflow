@php
    /** @var string $prefix Input name prefix: '' or 'branch_' */
    $prefix = $prefix ?? '';
    $model = $model ?? null;
    $idBase = $prefix === '' ? 'addr-' : 'branch-addr-';
    $field = static fn (string $key): string => $prefix.$key;
    $value = static fn (string $key) => old($field($key), $model?->{$key});
    $thaiPickerConfig = [
        'searchUrl' => route('addresses.thailand.subdistricts'),
    ];
@endphp

<div x-data="thaiSubdistrictPicker({{ \Illuminate\Support\Js::from($thaiPickerConfig) }})" class="contents">

<div>
    <label for="{{ $idBase }}no" class="form-label">{{ __('company.address_no') }}</label>
    <input type="text" name="{{ $field('address_no') }}" id="{{ $idBase }}no" value="{{ $value('address_no') }}" maxlength="50"
           class="form-input @error($field('address_no')) form-input-error @enderror">
    @error($field('address_no'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="{{ $idBase }}building" class="form-label">{{ __('company.address_building') }}</label>
    <input type="text" name="{{ $field('address_building') }}" id="{{ $idBase }}building" value="{{ $value('address_building') }}" maxlength="255"
           class="form-input @error($field('address_building')) form-input-error @enderror">
    @error($field('address_building'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

{{-- ซอย แล้วตามด้วย ถนน (แถวเดียวกัน: ซ้ายซอย ขวาถนน) --}}
<div>
    <label for="{{ $idBase }}soi" class="form-label">{{ __('company.address_soi') }}</label>
    <input type="text" name="{{ $field('address_soi') }}" id="{{ $idBase }}soi" value="{{ $value('address_soi') }}" maxlength="255"
           class="form-input @error($field('address_soi')) form-input-error @enderror">
    @error($field('address_soi'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="{{ $idBase }}street" class="form-label">{{ __('company.address_street') }}</label>
    <input type="text" name="{{ $field('address_street') }}" id="{{ $idBase }}street" value="{{ $value('address_street') }}" maxlength="255"
           class="form-input @error($field('address_street')) form-input-error @enderror">
    @error($field('address_street'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

{{-- ตำบล: ค้นหาแล้วเลือกจากรายการ (แสดง ตำบล » อำเภอ » จังหวัด » รหัสไปรษณีย์) — เลือกแล้วเติม อำเภอ/จังหวัด/รหัสไปรษณีย์ ให้อัตโนมัติ แต่ยังแก้ได้ทุกช่อง --}}
<div class="md:col-span-2 relative">
    <label for="{{ $idBase }}subdistrict" class="form-label">{{ __('company.address_subdistrict') }}</label>
    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('company.address_subdistrict_search_hint') }}</p>
    <input type="text" name="{{ $field('address_subdistrict') }}" id="{{ $idBase }}subdistrict" x-ref="subdistrict"
           value="{{ $value('address_subdistrict') }}" maxlength="120" autocomplete="off"
           @input="onSubdistrictInput($event)"
           @focus="onFocus()"
           @blur="onBlurSoon()"
           @keydown="onKeydown($event)"
           class="form-input @error($field('address_subdistrict')) form-input-error @enderror">
    @error($field('address_subdistrict'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div x-show="open && (results.length > 0 || loading)" x-cloak
         class="absolute z-50 left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg">
        <template x-if="loading">
            <div class="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">{{ __('company.address_search_loading') }}</div>
        </template>
        <template x-if="!loading">
            <template x-for="(item, index) in results" :key="item.i + '-' + index">
                <button type="button"
                        class="w-full text-left px-3 py-2 text-sm border-b border-slate-100 dark:border-slate-700 last:border-0 hover:bg-slate-100 dark:hover:bg-slate-700/80 text-slate-900 dark:text-slate-100"
                        :class="{ 'bg-slate-100 dark:bg-slate-700': highlighted === index }"
                        @mousedown.prevent="select(item)"
                        x-text="labelLine(item)"></button>
            </template>
        </template>
    </div>
</div>

<div>
    <label for="{{ $idBase }}district" class="form-label">{{ __('company.address_district') }}</label>
    <input type="text" name="{{ $field('address_district') }}" id="{{ $idBase }}district" x-ref="district" value="{{ $value('address_district') }}" maxlength="120"
           class="form-input @error($field('address_district')) form-input-error @enderror">
    @error($field('address_district'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="{{ $idBase }}province" class="form-label">{{ __('company.address_province') }}</label>
    <input type="text" name="{{ $field('address_province') }}" id="{{ $idBase }}province" x-ref="province" value="{{ $value('address_province') }}" maxlength="120"
           class="form-input @error($field('address_province')) form-input-error @enderror">
    @error($field('address_province'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

{{-- รหัสไปรษณีย์อยู่ท้ายสุด (หลังจังหวัด) --}}
<div>
    <label for="{{ $idBase }}postal" class="form-label">{{ __('company.address_postal_code') }}</label>
    <input type="text" name="{{ $field('address_postal_code') }}" id="{{ $idBase }}postal" x-ref="postal" value="{{ $value('address_postal_code') }}" maxlength="10"
           class="form-input @error($field('address_postal_code')) form-input-error @enderror">
    @error($field('address_postal_code'))
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

</div>
