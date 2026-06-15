<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentFormSubmission;
use App\Models\SubmissionActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $submissions = DocumentFormSubmission::query()
            ->where('user_id', $user->id)
            ->with('form:id,form_key,name,document_type')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $submissions->map(fn ($s) => [
                'id' => $s->id,
                'reference_no' => $s->reference_no,
                'status' => $s->status,
                'form' => $s->form ? [
                    'form_key' => $s->form->form_key,
                    'name' => $s->form->name,
                ] : null,
                'created_at' => $s->created_at?->toIso8601String(),
                'updated_at' => $s->updated_at?->toIso8601String(),
            ]),
            'meta' => [
                'pagination' => [
                    'total' => $submissions->total(),
                    'per_page' => $submissions->perPage(),
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                ],
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $submission = DocumentFormSubmission::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->with([
                'form:id,form_key,name,document_type',
                'instance.steps',
                'latestActivity',
            ])
            ->firstOrFail();

        $instance = $submission->instance;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'reference_no' => $submission->reference_no,
                'status' => $submission->status,
                'payload' => $submission->payload,
                'form' => $submission->form ? [
                    'form_key' => $submission->form->form_key,
                    'name' => $submission->form->name,
                ] : null,
                'workflow' => $instance ? [
                    'status' => $instance->status,
                    'current_step_no' => $instance->current_step_no,
                    'steps' => $instance->steps->map(fn ($step) => [
                        'step_no' => $step->step_no,
                        'name' => $step->name,
                        'action' => $step->action,
                        'acted_at' => $step->acted_at?->toIso8601String(),
                    ]),
                ] : null,
                'created_at' => $submission->created_at?->toIso8601String(),
                'updated_at' => $submission->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateDraft(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $submission = DocumentFormSubmission::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->firstOrFail();

        $payload = \App\Support\FormulaFields::recompute($submission->form, (array) $request->input('fields', []));
        $submission->update(['payload' => $payload]);

        SubmissionActivityLog::record($submission->id, $user->id, 'updated');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'updated_at' => $submission->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $submission = DocumentFormSubmission::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->firstOrFail();

        $submission->delete();

        return response()->json(['success' => true], 200);
    }
}
