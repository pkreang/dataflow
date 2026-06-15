<template x-teleport="body">
    <div x-data="{
            open: false,
            message: '',
            okLabel: '{{ __('common.confirm') }}',
            isDanger: false,
            pendingForm: null,
            init() {
                window.addEventListener('confirm-open', (e) => {
                    this.message   = e.detail.message  ?? '';
                    this.okLabel   = e.detail.okLabel  ?? '{{ __('common.confirm') }}';
                    this.isDanger  = e.detail.danger   ?? false;
                    this.pendingForm = e.detail.form   ?? null;
                    this.open = true;
                });
            },
            doConfirm() {
                this.open = false;
                const f = this.pendingForm;
                this.pendingForm = null;
                if (f) this.$nextTick(() => f.submit());
            },
            doCancel() {
                this.open = false;
                this.pendingForm = null;
            },
         }"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         @keydown.escape.window="doCancel()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 dark:bg-black/60"
             x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="doCancel()"
             aria-hidden="true"></div>

        {{-- Panel --}}
        <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700"
             x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             role="dialog" aria-modal="true" @click.stop>
            <p class="text-sm text-slate-700 dark:text-slate-300" x-text="message"></p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="doCancel()" class="btn-secondary">
                    {{ __('common.cancel') }}
                </button>
                <button type="button" @click="doConfirm()"
                        :class="isDanger ? 'btn-danger' : 'btn-primary'"
                        x-text="okLabel"></button>
            </div>
        </div>
    </div>
</template>
