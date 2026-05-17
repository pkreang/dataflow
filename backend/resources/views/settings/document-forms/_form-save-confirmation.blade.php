{{-- Save confirmation — teleported like preview --}}
<template x-teleport="body">
    <div x-show="showSaveConfirm" x-cloak
         class="fixed inset-0 z-[10000] flex items-center justify-center overflow-hidden p-4 sm:p-6"
         @keydown.escape.window="showSaveConfirm = false">
        <div x-show="showSaveConfirm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/50 dark:bg-black/60" @click="showSaveConfirm = false" aria-hidden="true"></div>
        <div x-show="showSaveConfirm" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-4 scale-[0.98]"
             class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"
             role="dialog" aria-modal="true" aria-labelledby="doc-form-save-confirm-title" @click.stop>
            <h3 id="doc-form-save-confirm-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('common.document_form_save_confirm_title') }}</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('common.document_form_save_confirm_message') }}</p>
            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button type="button" @click="showSaveConfirm = false"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">{{ __('common.cancel') }}</button>
                <button type="button" @click="confirmSave()"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700">{{ __('common.save') }}</button>
            </div>
        </div>
    </div>
</template>
