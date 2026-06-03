{{-- Preview Modal — teleported to <body> to escape stacking context --}}
<template x-teleport="body">
<div x-show="showPreview" x-cloak
     class="fixed inset-0 flex items-center justify-center overflow-hidden p-4 sm:p-6 md:p-8"
     style="z-index:9999"
     @keydown.escape.window="showPreview = false">

    {{-- Backdrop --}}
    <div x-show="showPreview" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-black/50 dark:bg-black/60" @click="showPreview = false"></div>

    {{-- Modal panel --}}
    <div x-show="showPreview" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-4 scale-[0.98]"
         class="document-form-preview-frame relative flex flex-col min-h-0 overflow-hidden rounded-2xl shadow-2xl bg-white dark:bg-gray-900 ring-1 ring-gray-200 dark:ring-gray-700">

        {{-- Header --}}
        <div class="shrink-0 flex items-center justify-between px-6 sm:px-8 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
            <div class="flex items-center gap-3 min-w-0">
                <div class="shrink-0 w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate" x-text="previewTitle || '{{ __('common.document_form_preview') }}'"></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('common.document_form_preview_hint') }}</p>
                </div>
            </div>
            <button type="button" @click="showPreview = false"
                    class="shrink-0 p-2 -mr-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    aria-label="{{ __('common.close') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Form preview body --}}
        <div class="document-form-preview-scroll flex-1 min-h-0 overflow-y-auto px-6 py-6 sm:px-10 sm:py-8 bg-white dark:bg-gray-900">
            <template x-if="fields.length === 0">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-base text-gray-400 dark:text-gray-500">{{ __('common.document_form_preview_empty') }}</p>
                </div>
            </template>

            <div class="grid gap-5 sm:gap-6" :style="`grid-template-columns: repeat(${layoutColumns}, minmax(0, 1fr))`">
            <template x-for="(field, idx) in fields" :key="'preview-'+field._rowId">
                <div :style="field.field_type === 'section' ? `grid-column: span ${layoutColumns}` : previewGridStyle(field)">
                    {{-- section divider --}}
                    <template x-if="field.field_type === 'section'">
                        <div class="pt-4 pb-2 first:pt-0">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 pb-2 border-b-2 border-blue-500/30 dark:border-blue-400/30" x-text="field.label_th || field.label_en || field.label || '{{ __('common.document_form_type_section') }}'"></h4>
                        </div>
                    </template>

                    <template x-if="field.field_type !== 'section'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            <span x-text="field.label_th || field.label_en || field.label || '{{ __('common.document_form_field_untitled') }}'"></span>
                            <span x-show="field.is_required" class="text-red-500 ml-0.5">*</span>
                        </label>
                    </template>

                    {{-- textarea --}}
                    <template x-if="field.field_type === 'textarea'">
                        <textarea readonly rows="3" tabindex="-1"
                                  :placeholder="field.placeholder || ''"
                                  class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none"></textarea>
                    </template>

                    {{-- select --}}
                    <template x-if="field.field_type === 'select'">
                        <select tabindex="-1" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none">
                            <option value="">{{ __('common.please_select') }}</option>
                            <template x-for="opt in (field.options_raw || '').split('\n').filter(o => o.trim())" :key="opt">
                                <option x-text="opt.trim()"></option>
                            </template>
                        </select>
                    </template>

                    {{-- multi_select --}}
                    <template x-if="field.field_type === 'multi_select'">
                        <select multiple tabindex="-1" class="mt-1.5 w-full h-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none">
                            <template x-for="opt in (field.options_raw || '').split('\n').filter(o => o.trim())" :key="opt">
                                <option x-text="opt.trim()"></option>
                            </template>
                        </select>
                    </template>

                    {{-- number --}}
                    <template x-if="field.field_type === 'number'">
                        <input type="number" step="0.01" readonly tabindex="-1"
                               :placeholder="field.placeholder || '0.00'"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- date --}}
                    <template x-if="field.field_type === 'date'">
                        <input type="date" readonly tabindex="-1" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- time --}}
                    <template x-if="field.field_type === 'time'">
                        <input type="time" readonly tabindex="-1" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- datetime --}}
                    <template x-if="field.field_type === 'datetime'">
                        <input type="datetime-local" readonly tabindex="-1" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- email --}}
                    <template x-if="field.field_type === 'email'">
                        <input type="email" readonly tabindex="-1" :placeholder="field.placeholder || 'name@example.com'" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- phone --}}
                    <template x-if="field.field_type === 'phone'">
                        <input type="tel" readonly tabindex="-1" :placeholder="field.placeholder || '0xx-xxx-xxxx'" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>

                    {{-- currency --}}
                    <template x-if="field.field_type === 'currency'">
                        <div class="relative mt-1.5">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm font-medium">฿</span>
                            <input type="number" step="0.01" readonly tabindex="-1" :placeholder="field.placeholder || '0.00'" class="w-full pl-8 pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                        </div>
                    </template>

                    {{-- checkbox --}}
                    <template x-if="field.field_type === 'checkbox'">
                        <div class="mt-2 space-y-2">
                            <template x-for="opt in (field.options_raw || '').split('\n').filter(o => o.trim())" :key="opt">
                                <label class="flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 pointer-events-none select-none">
                                    <input type="checkbox" tabindex="-1" class="h-4 w-4 rounded border-gray-300 dark:border-gray-500 text-blue-600 accent-blue-600">
                                    <span x-text="opt.trim()"></span>
                                </label>
                            </template>
                            <template x-if="!(field.options_raw || '').trim()">
                                <label class="flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 pointer-events-none select-none">
                                    <input type="checkbox" tabindex="-1" class="h-4 w-4 rounded border-gray-300 dark:border-gray-500 text-blue-600 accent-blue-600">
                                    <span x-text="field.label_th || field.label_en || field.label || '{{ __('common.document_form_field_untitled') }}'"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                    {{-- radio --}}
                    <template x-if="field.field_type === 'radio'">
                        <div class="mt-2 space-y-2">
                            <template x-for="opt in (field.options_raw || '').split('\n').filter(o => o.trim())" :key="opt">
                                <label class="flex items-center gap-2.5 text-sm text-gray-700 dark:text-gray-300 pointer-events-none select-none">
                                    <input type="radio" tabindex="-1" class="h-4 w-4 border-gray-300 dark:border-gray-500 text-blue-600 accent-blue-600">
                                    <span x-text="opt.trim()"></span>
                                </label>
                            </template>
                            <template x-if="!(field.options_raw || '').trim()">
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">{{ __('common.document_form_options_hint') }}</p>
                            </template>
                        </div>
                    </template>

                    {{-- file --}}
                    <template x-if="field.field_type === 'file'">
                        <div class="mt-1.5 flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 px-4 py-5 pointer-events-none select-none">
                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('common.document_form_type_file') }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('common.document_form_preview_file_hint') }}</p>
                            </div>
                        </div>
                    </template>

                    {{-- multi_file --}}
                    <template x-if="field.field_type === 'multi_file'">
                        <div class="mt-1.5 flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 px-4 py-5 pointer-events-none select-none">
                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M3 8h18M5 8a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V8z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('common.document_form_type_multi_file') }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">multiple · accept="image/*,application/pdf"</p>
                            </div>
                        </div>
                    </template>

                    {{-- image --}}
                    <template x-if="field.field_type === 'image'">
                        <div class="mt-1.5 flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 px-4 py-5 pointer-events-none select-none">
                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('common.document_form_type_image') }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">accept="image/*"</p>
                            </div>
                        </div>
                    </template>

                    {{-- signature --}}
                    <template x-if="field.field_type === 'signature'">
                        <div class="mt-1.5 w-full min-h-[6rem] rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 flex items-center justify-center pointer-events-none select-none">
                            <div class="text-center">
                                <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ __('common.document_form_type_signature') }}</span>
                            </div>
                        </div>
                    </template>

                    {{-- lookup --}}
                    <template x-if="field.field_type === 'lookup'">
                        <select tabindex="-1" class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none">
                            <option value="" x-text="field.lookup_source ? ('{{ __('common.please_select') }} — ' + (lookupSources[field.lookup_source]?.label_{{ app()->getLocale() }} || lookupSources[field.lookup_source]?.label_en || '')) : '{{ __('common.document_form_preview_lookup_pick_source') }}'"></option>
                        </select>
                    </template>

                    {{-- table --}}
                    <template x-if="field.field_type === 'table'">
                        <div class="mt-1.5">
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <template x-for="col in (field.table_columns || [])" :key="col.key">
                                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider border-b border-gray-200 dark:border-gray-600" x-text="col.label || col.key"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900">
                                        <tr>
                                            <template x-for="col in (field.table_columns || [])" :key="'cell-'+col.key">
                                                <td class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700">
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                </td>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">{{ __('common.document_form_table_add_row_hint') }}</p>
                        </div>
                    </template>

                    {{-- auto_number --}}
                    <template x-if="field.field_type === 'auto_number'">
                        <input type="text" readonly tabindex="-1" placeholder="Auto Generate"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-400 dark:text-gray-500 italic font-mono pointer-events-none select-none placeholder:italic focus:outline-none">
                    </template>

                    {{-- text (default) --}}
                    <template x-if="field.field_type === 'text' || (!field.field_type)">
                        <input type="text" readonly tabindex="-1"
                               :placeholder="field.placeholder || ''"
                               class="mt-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-gray-100 pointer-events-none select-none focus:outline-none">
                    </template>
                </div>
            </template>
            </div>
        </div>
    </div>
</div>
</template>
