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
        @include('settings.document-forms._form-preview-body')
    </div>
</div>
</template>
