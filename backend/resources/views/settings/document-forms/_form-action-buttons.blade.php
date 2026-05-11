{{-- Preview: outlined pill --}}
<div class="flex items-center px-2">
    <button type="button" @click="openPreview()"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-100">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        {{ __('common.document_form_preview') }}
    </button>
</div>
@if($isEdit)
    {{-- Policy: solid pill, vertically centred --}}
    <div class="flex items-center px-2">
        <a href="{{ route('settings.document-forms.policy.edit', $documentForm) }}"
           class="inline-flex items-center justify-center rounded-lg bg-purple-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-purple-700">{{ __('common.workflow_policy') }}</a>
    </div>
    {{-- Create report: build + submit a one-off form via JS instead of nesting a
         <form> in the toolbar. The nested-form pattern silently broke saving
         the parent document-form-builder — browsers close the outer <form> as
         soon as they hit the inner <form>, orphaning every later input so the
         POST body arrives empty and the server returns "required" on every
         basic field. JS-built form is appended to <body>, so no nesting. --}}
    <div class="flex items-center px-2">
        <button type="button"
                onclick="if (confirm('{{ __('common.form_report_create_confirm') }}')) {
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.action = '{{ route('settings.document-forms.create-report', $documentForm) }}';
                    const t = document.createElement('input');
                    t.type = 'hidden'; t.name = '_token'; t.value = '{{ csrf_token() }}';
                    f.appendChild(t);
                    document.body.appendChild(f);
                    f.submit();
                }"
                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700">
            {{ __('common.form_report_create_button') }}
        </button>
    </div>
@endif
{{-- Cancel: outlined pill --}}
<div class="flex items-center px-2">
    <a href="{{ route('settings.document-forms.index') }}"
       class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-100">{{ __('common.cancel') }}</a>
</div>
{{-- Save: opens confirm modal, then requestSubmit() --}}
<div class="flex items-center px-2">
    <button type="button" @click="openSaveConfirm()"
            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 min-w-[5rem]">{{ __('common.save') }}</button>
</div>
<a href="{{ route('settings.document-forms.index') }}"
   class="inline-flex items-center gap-1 self-stretch border-t-[3px] border-t-white dark:border-t-slate-800 px-3 text-sm font-medium text-slate-500 transition-colors hover:border-t-blue-400 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:border-t-blue-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    {{ __('common.back') }}
</a>
