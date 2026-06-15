<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;
        $roles = $user->getRoleNames()->all();
        $positionId = $user->position_id;

        $instances = ApprovalInstance::query()
            ->with(['workflow:id,name', 'requester:id,first_name,last_name', 'formSubmission:id,form_id,reference_no', 'formSubmission.form:id,form_key,name'])
            ->pendingForApprover($userId, $roles, $positionId)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $instances->map(fn ($i) => [
                'id'           => $i->id,
                'reference_no' => $i->reference_no,
                'status'       => $i->status,
                'document_type' => $i->document_type,
                'current_step_no' => $i->current_step_no,
                'requester'    => $i->requester ? [
                    'id'   => $i->requester->id,
                    'name' => $i->requester->first_name.' '.$i->requester->last_name,
                ] : null,
                'form' => $i->formSubmission?->form ? [
                    'form_key' => $i->formSubmission->form->form_key,
                    'name'     => $i->formSubmission->form->name,
                ] : null,
                'created_at' => $i->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'pagination' => [
                    'total'        => $instances->total(),
                    'per_page'     => $instances->perPage(),
                    'current_page' => $instances->currentPage(),
                    'last_page'    => $instances->lastPage(),
                ],
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->getRoleNames()->all();
        $positionId = $user->position_id;

        $instance = ApprovalInstance::query()
            ->with(['workflow', 'steps', 'requester:id,first_name,last_name,email', 'formSubmission.form.fields'])
            ->findOrFail($id);

        // Verify the user can act on this instance
        $canAct = ApprovalInstance::query()
            ->where('id', $id)
            ->pendingForApprover($user->id, $roles, $positionId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $instance->id,
                'reference_no'    => $instance->reference_no,
                'status'          => $instance->status,
                'document_type'   => $instance->document_type,
                'current_step_no' => $instance->current_step_no,
                'can_act'         => $canAct,
                'payload'         => $instance->payload,
                'requester'       => $instance->requester ? [
                    'id'    => $instance->requester->id,
                    'name'  => $instance->requester->first_name.' '.$instance->requester->last_name,
                    'email' => $instance->requester->email,
                ] : null,
                'form' => $instance->formSubmission?->form ? [
                    'form_key' => $instance->formSubmission->form->form_key,
                    'name'     => $instance->formSubmission->form->name,
                    'fields'   => $instance->formSubmission->form->fields
                        ->sortBy('sort_order')
                        ->map(fn ($f) => ['field_key' => $f->field_key, 'label' => $f->label, 'field_type' => $f->field_type])
                        ->values(),
                ] : null,
                'steps' => $instance->steps->map(fn ($step) => [
                    'step_no'   => $step->step_no,
                    'name'      => $step->name,
                    'action'    => $step->action,
                    'acted_at'  => $step->acted_at?->toIso8601String(),
                    'comment'   => $step->comment,
                ]),
                'created_at' => $instance->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function act(int $id, Request $request, ApprovalFlowService $approvalFlowService): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();

        try {
            $approvalFlowService->act(
                instanceId: $id,
                actorUserId: $user->id,
                action: $validated['action'],
                comment: $validated['comment'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $validated['action'] === 'approved' ? 'อนุมัติเรียบร้อย' : 'ปฏิเสธเรียบร้อย']);
    }
}
