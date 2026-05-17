{{--
    Document form field layout:
    - Mobile (< sm 640px): always 1 column (avoid cramming N fields side-by-side on phones)
    - sm+ : honour the form's layout_columns setting (1-4)
    Uses a CSS variable so Tailwind JIT doesn't need to know the column count at build time.
--}}
@props(['columns' => 1])
@php
    $layoutColumns = max(1, min(4, (int) $columns));
@endphp
<div {{ $attributes->merge(['class' => 'grid gap-4 form-field-grid']) }}
     style="--form-grid-cols: {{ $layoutColumns }};">
    {{ $slot }}
</div>
