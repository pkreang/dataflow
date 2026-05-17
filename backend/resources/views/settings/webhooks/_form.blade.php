@php
    $isEdit = isset($webhook);
    $action = $isEdit
        ? route('settings.webhooks.update', $webhook)
        : route('settings.webhooks.store');
    $currentEvents = old('events', $isEdit ? ($webhook->events ?? []) : []);
    $currentSecret = old('secret', $isEdit ? $webhook->secret : ($suggestedSecret ?? ''));
    $currentAllowlists = old('field_allowlists', $isEdit ? ($webhook->field_allowlists ?? []) : []);
    $forms = $forms ?? [];
@endphp

@if ($errors->any())
    <div class="alert-error mb-4">
        <ul class="text-sm space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('success'))
    <div class="alert-success mb-4"><p class="text-sm">{{ session('success') }}</p></div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-5" novalidate
      x-data="{
          testing: false,
          testResult: null,
          async runTest() {
              if (!{{ $isEdit ? 'true' : 'false' }}) return;
              this.testing = true;
              this.testResult = null;
              try {
                  const res = await fetch('{{ $isEdit ? route('settings.webhooks.test', $webhook) : '' }}', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                          'Accept': 'application/json',
                      },
                  });
                  this.testResult = await res.json();
              } catch (e) {
                  this.testResult = { ok: false, error: e.message };
              } finally {
                  this.testing = false;
              }
          }
      }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('common.name') }} <span class="text-red-500">*</span></label>
            <input name="name" value="{{ old('name', $webhook->name ?? '') }}" required
                   placeholder="{{ __('common.webhook_name_placeholder') }}"
                   class="form-input mt-1" />
        </div>
        <div class="flex items-end">
            <x-form.active-toggle name="is_active"
                :checked="old('is_active', $webhook->is_active ?? true)"
                label-class="block text-sm text-slate-600 dark:text-slate-300 mb-1" />
        </div>
    </div>

    <div>
        <label class="form-label">{{ __('common.webhook_url') }} <span class="text-red-500">*</span></label>
        <input name="url" value="{{ old('url', $webhook->url ?? '') }}" required type="url"
               placeholder="https://your-system.example.com/webhooks/dataflow"
               class="form-input mt-1 font-mono text-sm" />
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.webhook_url_hint') }}</p>
    </div>

    <div>
        <label class="form-label">{{ __('common.webhook_secret') }}</label>
        <div class="flex gap-2 mt-1">
            <input name="secret" value="{{ $currentSecret }}"
                   class="form-input flex-1 font-mono text-xs"
                   x-data x-init="$el.setAttribute('type','password')"
                   @focus="$el.setAttribute('type','text')"
                   @blur="$el.setAttribute('type','password')" />
            <button type="button"
                    @click="$root.querySelector('input[name=secret]').value = ([...crypto.getRandomValues(new Uint8Array(24))].map(b => b.toString(16).padStart(2,'0')).join(''))"
                    class="btn-secondary whitespace-nowrap">{{ __('common.regenerate') }}</button>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.webhook_secret_hint') }}</p>
    </div>

    <div>
        <label class="form-label">{{ __('common.events') }}</label>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ __('common.webhook_events_hint') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($events as $event)
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer">
                    <input type="checkbox" name="events[]" value="{{ $event }}"
                           {{ in_array($event, (array) $currentEvents, true) ? 'checked' : '' }}
                           class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-mono text-slate-700 dark:text-slate-300">{{ $event }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Per-form field allowlist --}}
    @if(count($forms) > 0)
    <div class="border-t border-slate-200 dark:border-slate-700 pt-5"
         x-data="Object.assign(webhookFieldAllowlist({{ Js::from($forms) }}, {{ Js::from((object) $currentAllowlists) }}), { previewOpen: false })">
        <div class="flex items-start justify-between gap-2 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('common.field_allowlist_title') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.field_allowlist_hint') }}</p>
            </div>
            <button type="button" @click="previewOpen = !previewOpen"
                    class="flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap shrink-0">
                <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-90': previewOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span x-text="previewOpen ? '{{ __('common.hide_sample_payload') }}' : '{{ __('common.show_sample_payload') }}'"></span>
            </button>
        </div>

        {{-- Live sample payload preview (at top so user sees payload shape first) --}}
        <div x-show="previewOpen" x-cloak class="mb-4">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('common.sample_payload_hint') }}</p>
            <pre class="rounded-lg bg-slate-900 dark:bg-slate-950 text-slate-100 p-3 overflow-x-auto text-[11px] leading-snug font-mono max-h-[420px]" x-text="samplePayloadJson(selectedForm)"></pre>
        </div>

        <div class="flex flex-wrap items-center gap-2 mb-3">
            <label class="text-xs text-slate-500 dark:text-slate-400">{{ __('common.select_form_to_configure') }}:</label>
            <select x-model="selectedForm" class="form-input text-sm py-1 px-2 w-auto">
                <option value="">—</option>
                <template x-for="f in forms" :key="f.form_key">
                    <option :value="f.form_key" x-text="f.name + (configuredCount(f.form_key) > 0 ? ' (' + configuredCount(f.form_key) + ')' : '')"></option>
                </template>
            </select>
            <button type="button" x-show="selectedForm && hasAllowlist(selectedForm)"
                    @click="resetForm(selectedForm)"
                    class="text-xs text-red-600 dark:text-red-400 hover:underline">{{ __('common.reset_to_all') }}</button>
        </div>

        <template x-if="selectedForm && getFormFields(selectedForm).length > 0">
            <div>
                <div class="flex items-center justify-between mb-2 gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400"
                          x-text="`${(allowlists[selectedForm] || []).length}/${getFormFields(selectedForm).length}`"></span>
                    <div class="flex gap-2">
                        <button type="button" @click="selectAll(selectedForm)" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">{{ __('common.select_all') }}</button>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <button type="button" @click="clearAll(selectedForm)" class="text-xs text-slate-500 dark:text-slate-400 hover:underline">{{ __('common.clear_all') }}</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/20 p-3 max-h-[260px] overflow-y-auto">
                    <template x-for="field in getFormFields(selectedForm)" :key="field.key">
                        <label class="inline-flex items-center gap-2 min-w-0 cursor-pointer" :title="field.label">
                            <input type="checkbox"
                                   :checked="isChecked(selectedForm, field.key)"
                                   @change="toggle(selectedForm, field.key)"
                                   class="rounded text-blue-600 shrink-0">
                            <span class="text-sm text-slate-700 dark:text-slate-300 truncate" x-text="field.label"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>

        <div x-show="Object.keys(allowlists).filter(k => (allowlists[k] || []).length > 0).length > 0" class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            {{ __('common.configured_forms') }}:
            <template x-for="key in Object.keys(allowlists).filter(k => (allowlists[k] || []).length > 0)" :key="key">
                <span class="inline-block ml-1 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                    <span x-text="formNameByKey(key)"></span>
                    <span class="text-blue-500 dark:text-blue-400" x-text="`(${allowlists[key].length})`"></span>
                </span>
            </template>
        </div>

        {{-- Hidden inputs: render one per allowed field, name=field_allowlists[form_key][] --}}
        <template x-for="(fields, fkey) in allowlists" :key="fkey">
            <template x-for="(field, i) in fields" :key="fkey + ':' + field">
                <input type="hidden" :name="`field_allowlists[${fkey}][]`" :value="field">
            </template>
        </template>
    </div>
    @endif

    @if($isEdit)
        <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('common.webhook_test_send') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('common.webhook_test_hint') }}</p>
                </div>
                <button type="button" @click="runTest()" class="btn-secondary whitespace-nowrap" :disabled="testing">
                    <span x-show="!testing">{{ __('common.send_test') }}</span>
                    <span x-show="testing" x-cloak>{{ __('common.sending') }}...</span>
                </button>
            </div>
            <div x-show="testResult" x-cloak class="mt-3 rounded-lg p-3 text-xs font-mono"
                 :class="testResult?.ok ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200'">
                <template x-if="testResult?.ok">
                    <div>
                        <p class="font-semibold">{{ __('common.success') }} — HTTP <span x-text="testResult?.status"></span></p>
                        <pre class="mt-1 whitespace-pre-wrap break-all opacity-80" x-text="testResult?.body"></pre>
                    </div>
                </template>
                <template x-if="!testResult?.ok">
                    <div>
                        <p class="font-semibold">{{ __('common.failed') }} <span x-show="testResult?.status">— HTTP <span x-text="testResult?.status"></span></span></p>
                        <pre class="mt-1 whitespace-pre-wrap break-all opacity-80" x-text="testResult?.error || testResult?.body"></pre>
                    </div>
                </template>
            </div>
        </div>
    @endif

    <div class="flex flex-wrap justify-end gap-3 pt-2">
        <a href="{{ route('settings.webhooks.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
        <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
    </div>
</form>

<script>
function webhookFieldAllowlist(forms, initial) {
    // initial = JS object keyed by form_key → array of field_keys
    // Js::from cast a PHP object so Alpine sees a plain object
    return {
        forms: forms || [],
        selectedForm: '',
        allowlists: Object.assign({}, initial || {}),

        getFormFields(formKey) {
            const f = this.forms.find(x => x.form_key === formKey);
            return f ? f.fields : [];
        },

        formNameByKey(formKey) {
            const f = this.forms.find(x => x.form_key === formKey);
            return f ? f.name : formKey;
        },

        isChecked(formKey, fieldKey) {
            const arr = this.allowlists[formKey] || [];
            return arr.includes(fieldKey);
        },

        toggle(formKey, fieldKey) {
            const arr = (this.allowlists[formKey] || []).slice();
            const idx = arr.indexOf(fieldKey);
            if (idx === -1) arr.push(fieldKey); else arr.splice(idx, 1);
            this.allowlists[formKey] = arr;
        },

        selectAll(formKey) {
            this.allowlists[formKey] = this.getFormFields(formKey).map(f => f.key);
        },

        clearAll(formKey) {
            this.allowlists[formKey] = [];
        },

        resetForm(formKey) {
            delete this.allowlists[formKey];
            this.allowlists = Object.assign({}, this.allowlists);
        },

        hasAllowlist(formKey) {
            return Array.isArray(this.allowlists[formKey]) && this.allowlists[formKey].length > 0;
        },

        configuredCount(formKey) {
            return (this.allowlists[formKey] || []).length;
        },

        // Build a representative sample payload for the chosen form, using the
        // latest real submission's payload when available — falls back to
        // <label> placeholders for fields the latest submission did not fill.
        samplePayloadJson(formKey) {
            const target = formKey || (this.forms[0]?.form_key ?? null);
            if (!target) return JSON.stringify({ event: 'form.submitted', data: {} }, null, 2);
            const form = this.forms.find(x => x.form_key === target);
            const allowedKeys = (this.allowlists[target] && this.allowlists[target].length > 0)
                ? this.allowlists[target]
                : (form ? form.fields.map(f => f.key) : []);

            const real = form?.sample_payload ?? null;
            const samplePayload = {};
            for (const k of allowedKeys) {
                const f = form?.fields.find(x => x.key === k);
                if (real && real[k] !== undefined && real[k] !== null && real[k] !== '') {
                    samplePayload[k] = real[k];
                } else {
                    samplePayload[k] = '<' + (f?.label || k) + '>';
                }
            }

            const envelope = {
                event: 'form.submitted',
                webhook_id: 1,
                sent_at: '2026-05-13T12:34:56+07:00',
                data: {
                    submission: {
                        id: 123,
                        form_id: 5,
                        form_key: target,
                        form_name: form?.name ?? target,
                        reference_no: form?.sample_reference_no ?? 'REF-2605-0123',
                        status: 'submitted',
                        created_at: '2026-05-13T12:30:00+07:00',
                        payload: samplePayload,
                    },
                    requester: {
                        id: 42,
                        name: 'สมชาย ใจดี',
                        email: 'somchai@example.com',
                        department_id: 3,
                        department_name: 'ฝ่ายควบคุมคุณภาพ',
                    },
                    approval_instance_id: 99,
                },
            };
            return JSON.stringify(envelope, null, 2);
        },
    };
}
</script>
