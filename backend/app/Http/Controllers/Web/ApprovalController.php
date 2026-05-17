<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalFlowService $approvalFlow,
    ) {}

    public function myApprovals(): View
    {
        $user = session('user');
        $userId = (int) ($user['id'] ?? 0);
        $rawRoles = $user['roles'] ?? [];
        $roles = collect($rawRoles)
            ->map(fn ($r) => is_array($r) ? ($r['name'] ?? '') : $r)
            ->filter()
            ->values()
            ->all();

        $actorPositionId = User::query()->whereKey($userId)->value('position_id');

        // Profile signature pre-fill — when present, the signature pad starts
        // with this image loaded so approvers don't have to redraw every time.
        // Always passed to the view; the pad ignores it when null.
        $mySignatureDataUrl = $userId
            ? User::query()->whereKey($userId)->value('signature_path')
            : null;

        $instances = ApprovalInstance::query()
            ->from('approval_instances')
            ->with(['steps', 'workflow', 'requester', 'formSubmission'])
            ->where('approval_instances.status', 'pending')
            ->where(function ($q) use ($userId) {
                $q->where('approval_instances.requester_user_id', '!=', $userId)
                    ->orWhereHas('workflow', fn ($w) => $w->where('allow_requester_as_approver', true));
            })
            ->whereHas('steps', function ($q) use ($userId, $roles, $actorPositionId) {
                $q->where('approval_instance_steps.action', 'pending')
                    ->whereRaw('approval_instance_steps.step_no = approval_instances.current_step_no')
                    ->where(function ($sq) use ($userId, $roles, $actorPositionId) {
                        $sq->where(function ($uq) use ($userId) {
                            $uq->where('approver_type', 'user')
                                ->where('approver_ref', (string) $userId);
                        });
                        if (! empty($roles)) {
                            $sq->orWhere(function ($rq) use ($roles) {
                                $rq->where('approver_type', 'role')
                                    ->whereIn('approver_ref', $roles);
                            });
                        }
                        if ($actorPositionId) {
                            $sq->orWhere(function ($pq) use ($actorPositionId) {
                                $pq->where('approver_type', 'position')
                                    ->where('approver_ref', (string) $actorPositionId);
                            });
                        }
                    });
            })
            ->latest()
            ->get();

        return view('approvals.my-approvals', compact('instances', 'mySignatureDataUrl'));
    }

    public function act(Request $request, ApprovalInstance $instance, ApprovalFlowService $approvalFlowService): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
            // Either a base64 PNG/JPG data URL captured from the canvas, or a
            // public URL (when pre-loaded from profile). Soft cap of 1MB ≈ 1.4MB
            // base64 to allow some headroom for encoding overhead.
            'signature_image' => 'nullable|string|max:1500000',
        ]);

        $userId = (int) (session('user.id') ?? 0);
        $action = $validated['action'];
        $signature = $validated['signature_image'] ?? null;
        if (is_string($signature) && $signature !== '' && ! preg_match('/^(data:image\/|https?:\/\/)/', $signature)) {
            $signature = null;
        }

        try {
            $approvalFlowService->act(
                $instance->id,
                $userId,
                $action,
                $validated['comment'] ?? null,
                $signature
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage() === 'signature_required'
                ? __('common.approval_signature_required_error')
                : $e->getMessage();
            return back()->withErrors(['approval' => $message]);
        }

        $fresh = $instance->fresh(['steps']);
        $currentStep = $fresh->steps->firstWhere('step_no', $instance->current_step_no);
        $minApprovals = $currentStep?->min_approvals ?? 1;
        $approvedCount = count($currentStep?->approved_by ?? []);

        if ($action === 'rejected') {
            $message = __('common.approval_rejected_success');
        } elseif ($minApprovals > 1 && $approvedCount < $minApprovals) {
            $message = __('common.approval_partial_success', ['count' => $approvedCount, 'total' => $minApprovals]);
        } else {
            $message = __('common.approval_approved_success');
        }

        // Detect mobile referrer → redirect back to /m/approvals instead of desktop
        $fromMobile = str_contains((string) $request->header('Referer', ''), '/m/');
        $fallback = $fromMobile
            ? redirect()->route('mobile.approvals')->with('success', $message)
            : redirect()->route('approvals.my')->with('success', $message);

        return $this->redirectForDynamicForm($fresh, $message) ?? $fallback;
    }

    public function updateFields(Request $request, ApprovalInstance $instance): RedirectResponse
    {
        abort_unless($instance->status === 'pending', 403);
        abort_unless(in_array('approval.approve', session('user_permissions', []), true), 403);

        $userId = (int) (session('user.id') ?? 0);
        $instance->load(['steps', 'workflow']);
        $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);

        abort_unless($currentStep && $currentStep->action === 'pending', 403);
        abort_unless($this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId), 403);

        $stepRole = 'step_'.$instance->current_step_no;

        $form = DocumentForm::query()
            ->with('fields')
            ->where('document_type', $instance->document_type)
            ->where('is_active', true)
            ->first();

        abort_unless($form, 404);

        $userToken = 'user:'.$userId;
        $editableKeys = $form->fields
            ->filter(fn ($f) => $f->field_type !== 'file'
                && (in_array($stepRole, $f->effective_editable_by, true)
                    || in_array($userToken, $f->effective_editable_by, true)))
            ->pluck('field_key')
            ->toArray();

        $submitted = $request->input('field_updates', []);
        $safeUpdates = array_intersect_key($submitted, array_flip($editableKeys));

        $payload = $instance->payload ?? [];
        foreach ($safeUpdates as $key => $value) {
            $payload[$key] = $value;
        }

        $instance->update(['payload' => $payload]);

        return redirect()->back()->with('success', __('common.saved'));
    }

    private function redirectForDynamicForm(ApprovalInstance $instance, string $message = ''): ?RedirectResponse
    {
        $submission = DocumentFormSubmission::where('approval_instance_id', $instance->id)->first();
        if ($submission) {
            return redirect()->route('forms.submission.show', $submission)->with('success', $message ?: __('common.saved'));
        }

        return null;
    }
}
