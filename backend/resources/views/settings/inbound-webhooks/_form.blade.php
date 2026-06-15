@php
    $webhook = $webhook ?? null;
    $forms = $forms ?? collect();
    $suggestedToken = $suggestedToken ?? null;
    $endpointUrl = $endpointUrl ?? null;
    $isEdit = $webhook !== null;
    $name = old('name', $webhook->name ?? '');
    $slug = old('slug', $webhook->slug ?? '');
    $token = old('token', $webhook->token ?? $suggestedToken ?? '');
    $documentFormId = old('document_form_id', $webhook->document_form_id ?? null);
    $isActive = old('is_active', $webhook->is_active ?? true);
@endphp

<div class="space-y-5 max-w-3xl">
    @if ($errors->any())
        <div class="alert-error">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('common.name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $name }}" required class="form-input" placeholder="e.g. รับแจ้งซ่อมจาก IoT">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('common.incoming_webhook_target_form') }} <span class="text-red-500">*</span></label>
            <select name="document_form_id" required class="form-input">
                <option value="">— select —</option>
                @foreach ($forms as $f)
                    <option value="{{ $f->id }}" @selected((int) $documentFormId === (int) $f->id)>{{ $f->name }} ({{ $f->form_key }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('common.slug') }}</label>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 font-mono">/api/inbound/</span>
            <input type="text" name="slug" value="{{ $slug }}" class="form-input font-mono" placeholder="auto-generated if blank">
        </div>
        <p class="text-xs text-slate-500 mt-1">a-z, 0-9, dashes — must be unique. Leave blank to auto-generate.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('common.incoming_webhook_token') }}</label>
        <div class="flex items-center gap-2" x-data="{ show: false }">
            <input :type="show ? 'text' : 'password'" name="token" value="{{ $token }}" class="form-input font-mono text-xs">
            <button type="button" @click="show = !show" class="btn-secondary text-xs whitespace-nowrap"
                    x-text="show ? '{{ __('common.hide') }}' : '{{ __('common.show') }}'"></button>
        </div>
        <p class="text-xs text-slate-500 mt-1">External system must send this in <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">X-Webhook-Token</code> header.</p>
    </div>

    @if ($endpointUrl)
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-900/20 p-4">
            <p class="text-xs font-medium text-emerald-900 dark:text-emerald-200 mb-2">{{ __('common.incoming_webhook_endpoint') }}</p>
            <code class="block text-xs font-mono text-emerald-900 dark:text-emerald-200 break-all select-all">{{ $endpointUrl }}</code>
            <p class="text-xs text-emerald-800 dark:text-emerald-300 mt-3">{{ __('common.incoming_webhook_curl_hint') }}</p>
            <pre class="mt-1 text-[11px] font-mono text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900 p-3 rounded overflow-x-auto select-all">curl -X POST "{{ $endpointUrl }}" \
  -H "X-Webhook-Token: {{ $token }}" \
  -H "Content-Type: application/json" \
  -d '{"title":"Sample","location":"Line 1"}'</pre>
        </div>
    @endif

    <div>
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($isActive) class="rounded">
            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('common.active') }}</span>
        </label>
    </div>

    <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
        <button type="submit" class="btn-primary">{{ __('common.save') }}</button>
        <a href="{{ route('settings.inbound-webhooks.index') }}" class="btn-secondary">{{ __('common.cancel') }}</a>
    </div>
</div>
