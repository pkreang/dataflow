<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class WebhookController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'webhooks_per_page');
        $webhooks = Webhook::query()->orderBy('name')->paginate($perPage)->withQueryString();

        return view('settings.webhooks.index', compact('webhooks', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.webhooks.create', [
            'events' => Webhook::EVENTS,
            'suggestedSecret' => Webhook::generateSecret(),
            'forms' => $this->formsWithFields(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        Webhook::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?: Webhook::generateSecret(),
            'events' => $validated['events'] ?? [],
            'field_allowlists' => $this->normalizeAllowlists($validated['field_allowlists'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('settings.webhooks.index')->with('success', __('common.saved'));
    }

    public function edit(Webhook $webhook): View
    {
        return view('settings.webhooks.edit', [
            'webhook' => $webhook,
            'events' => Webhook::EVENTS,
            'forms' => $this->formsWithFields(),
        ]);
    }

    /**
     * Active forms with their selectable fields — used to populate the per-form
     * field allowlist picker on the webhook edit page. Strips file/signature/
     * structural fields that we never expose externally anyway.
     */
    private function formsWithFields(): array
    {
        $skipTypes = ['section', 'page_break', 'qr_code', 'auto_number', 'signature', 'file', 'multi_file', 'image'];

        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('name')
            ->get(['id', 'form_key', 'name']);

        // Pull the most recent submission per form so the preview can show
        // real values instead of placeholders — gives a more authentic sample.
        $latestSubmissions = DocumentFormSubmission::query()
            ->whereIn('form_id', $forms->pluck('id'))
            ->select('form_id', 'payload', 'reference_no')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('form_id')
            ->map(fn ($group) => $group->first());

        return $forms->map(function ($form) use ($skipTypes, $latestSubmissions) {
            $fields = $form->fields
                ->reject(fn ($f) => in_array($f->field_type, $skipTypes, true))
                ->map(fn ($f) => [
                    'key' => $f->field_key,
                    'label' => $f->label ?: $f->field_key,
                ])
                ->values()
                ->all();

            $latest = $latestSubmissions->get($form->id);

            return [
                'form_key' => $form->form_key,
                'name' => $form->name,
                'fields' => $fields,
                'sample_payload' => $latest?->payload ?? null,
                'sample_reference_no' => $latest?->reference_no ?? null,
            ];
        })
            ->filter(fn ($f) => count($f['fields']) > 0)
            ->values()
            ->all();
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $webhook->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?: $webhook->secret,
            'events' => $validated['events'] ?? [],
            'field_allowlists' => $this->normalizeAllowlists($validated['field_allowlists'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('settings.webhooks.edit', $webhook)->with('success', __('common.updated'));
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return redirect()->route('settings.webhooks.index')->with('success', __('common.deleted'));
    }

    public function testSend(Webhook $webhook): JsonResponse
    {
        $payload = [
            'event' => 'test',
            'webhook_id' => $webhook->id,
            'sent_at' => now()->toIso8601String(),
            'sample' => true,
            'message' => 'Test ping from Data Flow webhook hub.',
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Webhook-Event' => 'test',
                    'X-Webhook-Signature' => $signature,
                ])
                ->post($webhook->url, $payload);

            $webhook->update([
                'last_triggered_at' => now(),
                'last_response_status' => $response->status(),
            ]);

            return response()->json([
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            $webhook->update([
                'last_triggered_at' => now(),
                'last_response_status' => 0,
            ]);

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    private function validateRequest(Request $request): array
    {
        $allowedEvents = implode(',', Webhook::EVENTS);

        return $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'secret' => 'nullable|string|min:16|max:128',
            'events' => 'nullable|array',
            'events.*' => "string|in:{$allowedEvents}",
            'field_allowlists' => 'nullable|array',
            'field_allowlists.*' => 'array',
            'field_allowlists.*.*' => 'string|max:120',
            'is_active' => 'nullable|boolean',
        ]);
    }

    /**
     * Drop empty entries: forms with no selected fields fall back to default
     * (= send all). Returns null when nothing is configured at all.
     */
    private function normalizeAllowlists(?array $allowlists): ?array
    {
        if (! is_array($allowlists)) {
            return null;
        }
        $clean = [];
        foreach ($allowlists as $formKey => $fields) {
            if (! is_string($formKey) || $formKey === '') {
                continue;
            }
            if (! is_array($fields)) {
                continue;
            }
            $fields = array_values(array_unique(array_filter($fields, fn ($f) => is_string($f) && $f !== '')));
            if (count($fields) > 0) {
                $clean[$formKey] = $fields;
            }
        }

        return count($clean) > 0 ? $clean : null;
    }
}
