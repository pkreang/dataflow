<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentFormSubmission;
use App\Models\IncomingWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundController extends Controller
{
    public function receive(Request $request, string $slug): JsonResponse
    {
        $webhook = IncomingWebhook::where('slug', $slug)->where('is_active', true)->first();
        abort_if(! $webhook, 404, 'Inbound webhook not found or inactive');

        $provided = (string) ($request->header('X-Webhook-Token') ?? $request->bearerToken() ?? '');
        abort_if(! hash_equals($webhook->token, $provided), 401, 'Invalid token');

        $form = $webhook->form()->where('is_active', true)->first();
        abort_if(! $form, 410, 'Linked form no longer active');

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $allowedKeys = $form->fields()->pluck('field_key')->all();
        $filtered = array_intersect_key($payload, array_flip($allowedKeys));

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => null,
            'department_id' => null,
            'org_unit_id' => null,
            'payload' => $filtered,
            'status' => 'draft',
        ]);

        $webhook->forceFill([
            'last_received_at' => now(),
            'received_count' => $webhook->received_count + 1,
            'last_payload' => $payload,
        ])->save();

        return response()->json([
            'ok' => true,
            'submission_id' => $submission->id,
            'received_keys' => array_keys($filtered),
            'ignored_keys' => array_values(array_diff(array_keys($payload), array_keys($filtered))),
        ], 201);
    }
}
