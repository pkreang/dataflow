@props([
    'name' => 'search',
    'placeholder' => null,
    'value' => null,
    'mode' => 'submit',
])
@php
    $placeholder = $placeholder ?? __('common.search') . '...';
    $value = $value ?? request($name, '');
@endphp

@if($mode === 'ajax')
    {{-- AJAX mode: Alpine x-model with debounce --}}
    <div class="relative max-w-sm">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <input type="text"
               x-model="{{ $name }}"
               placeholder="{{ $placeholder }}"
               class="form-input !pl-10">
        <div x-show="loading" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <svg class="w-4 h-4 text-slate-400 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
    </div>
@else
    {{-- Submit mode: plain form input --}}
    <div class="relative max-w-sm">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <input type="text"
               name="{{ $name }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               class="form-input !pl-10">
    </div>
@endif
