{{--
    Field-type palette for the form builder (left column).
    Each button drags-OR-clicks into the canvas via addField(type).
    SortableJS wires drag-to-add in `formBuilder().mountPalette()`.
--}}
@php
    $paletteCategories = [
        'basic' => [
            ['type' => 'text', 'icon' => 'M4 6h16M4 12h10M4 18h16'],
            ['type' => 'textarea', 'icon' => 'M4 6h16M4 10h16M4 14h12M4 18h8'],
            ['type' => 'number', 'icon' => 'M7 8h10M9 12h6M5 16h14M5 8l-1 8'],
            ['type' => 'currency', 'icon' => 'M12 4v16M8 8h6a2 2 0 010 4H10a2 2 0 000 4h6'],
            ['type' => 'date', 'icon' => 'M8 7V3m8 4V3M4 11h16M5 7h14a1 1 0 011 1v11a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1z'],
            ['type' => 'time', 'icon' => 'M12 8v4l2 2m-2 8a8 8 0 100-16 8 8 0 000 16z'],
            ['type' => 'datetime', 'icon' => 'M12 8v4l2 1m6-1a8 8 0 10-2.5 5.8M21 16v5h-5'],
            ['type' => 'email', 'icon' => 'M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8a2 2 0 012-2h14a2 2 0 012 2'],
            ['type' => 'phone', 'icon' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5l1.5-2.5L20 15v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z'],
            ['type' => 'signature', 'icon' => 'M3 17l6-6 4 4 8-8M17 5h2v2'],
        ],
        'choice' => [
            ['type' => 'select', 'icon' => 'M19 9l-7 7-7-7'],
            ['type' => 'multi_select', 'icon' => 'M5 7l3 3 5-5M5 14l3 3 5-5M16 8h5M16 16h5'],
            ['type' => 'radio', 'icon' => 'M12 18a6 6 0 100-12 6 6 0 000 12zm0-3a3 3 0 100-6 3 3 0 000 6z'],
            ['type' => 'checkbox', 'icon' => 'M5 13l4 4L19 7'],
        ],
        'advanced' => [
            ['type' => 'lookup', 'icon' => 'M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z'],
            ['type' => 'file', 'icon' => 'M9 13h6m-3-3v6m9-2V7a2 2 0 00-2-2h-7l-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2z'],
            ['type' => 'multi_file', 'icon' => 'M4 4h12v3H4zm0 7h12v3H4zm0 7h16v3H4z'],
            ['type' => 'image', 'icon' => 'M4 5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm12 12l-4-5-3 4-2-3-3 4'],
            ['type' => 'table', 'icon' => 'M3 7h18M3 12h18M3 17h18M7 3v18M17 3v18'],
            ['type' => 'qr_code', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM18 18h3v3h-3z'],
            ['type' => 'auto_number', 'icon' => 'M4 6h12M4 12h12M4 18h8M18 4l2 2-2 2M18 12l2 2-2 2M14 18l2 2-2 2'],
        ],
        'layout' => [
            ['type' => 'section', 'icon' => 'M4 6h16M4 12h16M4 18h16'],
            ['type' => 'page_break', 'icon' => 'M3 8h6M15 8h6M3 16h6M15 16h6M9 12h6'],
            ['type' => 'group', 'icon' => 'M4 5a1 1 0 011-1h6v6H4V5zm9-1h6a1 1 0 011 1v6h-7V4zM4 13h7v7H5a1 1 0 01-1-1v-6zm9 0h7v6a1 1 0 01-1 1h-6v-7z'],
        ],
    ];
@endphp

<aside class="hidden lg:flex flex-col gap-4 sticky top-20 self-start max-h-[calc(100vh-6rem)] overflow-y-auto pr-2">
    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
            {{ __('common.document_form_palette_title') }}
        </h3>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            {{ __('common.document_form_palette_hint') }}
        </p>
    </div>

    <div data-palette
         class="flex flex-col gap-4">
        @foreach($paletteCategories as $categoryKey => $items)
            <div>
                <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1.5">
                    {{ __('common.document_form_palette_category_'.$categoryKey) }}
                </h4>
                <div data-palette-group="{{ $categoryKey }}" class="grid grid-cols-2 gap-1">
                    @foreach($items as $item)
                        @php $btnLabel = __('common.document_form_type_'.$item['type']); @endphp
                        <button type="button"
                                @click="addField('{{ $item['type'] }}')"
                                data-field-type="{{ $item['type'] }}"
                                draggable="true"
                                title="{{ $btnLabel }}"
                                class="palette-item group flex items-center gap-1 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-1.5 py-1.5 text-left text-[11px] font-medium text-slate-700 dark:text-slate-200 transition-all hover:border-blue-400 hover:bg-blue-50 dark:hover:border-blue-500 dark:hover:bg-blue-900/20 cursor-grab active:cursor-grabbing min-w-0">
                            <svg class="w-3.5 h-3.5 shrink-0 text-slate-500 dark:text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                            </svg>
                            <span class="line-clamp-2 leading-tight min-w-0">{{ $btnLabel }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</aside>
