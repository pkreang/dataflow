<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\IncomingWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncomingWebhookController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'incoming_webhooks_per_page');

        $query = IncomingWebhook::query()->with('form');
        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $webhooks = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('settings.inbound-webhooks.index', compact('webhooks', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.inbound-webhooks.create', [
            'forms' => $this->activeForms(),
            'suggestedToken' => IncomingWebhook::generateToken(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $slug = $validated['slug'] ?: IncomingWebhook::generateSlug($validated['name']);

        IncomingWebhook::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'token' => $validated['token'] ?: IncomingWebhook::generateToken(),
            'document_form_id' => $validated['document_form_id'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('settings.inbound-webhooks.index')->with('success', __('common.saved'));
    }

    public function edit(IncomingWebhook $inbound_webhook): View
    {
        return view('settings.inbound-webhooks.edit', [
            'webhook' => $inbound_webhook,
            'forms' => $this->activeForms(),
            'endpointUrl' => url("/api/inbound/{$inbound_webhook->slug}"),
        ]);
    }

    public function update(Request $request, IncomingWebhook $inbound_webhook): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $inbound_webhook->update(['is_active' => ! $inbound_webhook->is_active]);

            return redirect()->route('settings.inbound-webhooks.index')->with('success', __('common.saved'));
        }

        $validated = $this->validateRequest($request, $inbound_webhook->id);

        $inbound_webhook->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: $inbound_webhook->slug,
            'token' => $validated['token'] ?: $inbound_webhook->token,
            'document_form_id' => $validated['document_form_id'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('settings.inbound-webhooks.index')->with('success', __('common.saved'));
    }

    public function destroy(IncomingWebhook $inbound_webhook): RedirectResponse
    {
        $inbound_webhook->delete();

        return redirect()->route('settings.inbound-webhooks.index')->with('success', __('common.deleted'));
    }

    public function testReceive(Request $request, IncomingWebhook $inbound_webhook): JsonResponse
    {
        abort_if(! $inbound_webhook->is_active, 422, 'Webhook is inactive — activate first.');

        $form = $inbound_webhook->form;
        if (! $form) {
            return response()->json(['ok' => false, 'error' => 'Linked form missing'], 422);
        }

        // Build a sample payload using first 3 text-like fields
        $sample = [];
        foreach ($form->fields()->orderBy('sort_order')->limit(5)->get() as $f) {
            if (in_array($f->field_type, ['text', 'textarea'], true)) {
                $sample[$f->field_key] = '[Test] '.($f->label ?? $f->field_key);
            } elseif ($f->field_type === 'number') {
                $sample[$f->field_key] = 1;
            }
        }

        $url = url("/api/inbound/{$inbound_webhook->slug}");
        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Webhook-Token' => $inbound_webhook->token])
                ->acceptJson()
                ->post($url, $sample);

            return response()->json([
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
                'sample_sent' => $sample,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique('incoming_webhooks', 'slug')->ignore($ignoreId),
            ],
            'token' => ['nullable', 'string', 'min:16', 'max:96'],
            'document_form_id' => ['required', 'integer', Rule::exists('document_forms', 'id')->where('is_active', true)],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function activeForms()
    {
        return DocumentForm::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'form_key', 'document_type']);
    }
}
