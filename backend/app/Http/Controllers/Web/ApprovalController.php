<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\SubmissionActivityLog;
use App\Services\ApprovalFlowService;
use App\Services\ApproverIdentity;
use App\Services\FormSchemaService;
use App\Support\PayloadDiffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalFlowService $approvalFlow,
        protected FormSchemaService $schemaService,
    ) {}

    public function myApprovals(ApproverIdentity $approverIdentity): View
    {
        $identity = $approverIdentity->fromSession();
        $userId = $identity['userId'];

        // Inbox is now a per-document-type table; the act/sign happens on each
        // document's detail page (forms.submission.show / legacy show views).
        // Two zones: (a) pending = awaiting my action, (b) history = documents I
        // already acted on (approved/rejected any step), so an approver keeps
        // sight of work they handled after it leaves their pending queue.
        //
        // Resolve the two ID sets cheaply first (no eager-load → no larastan
        // relation noise; the steps query also dodges the whereHas false
        // positive), then hydrate ONCE so there is a single ->with() over
        // ApprovalInstance. formSubmission stays eager-loaded so detailUrl()
        // doesn't N+1.
        $pendingIds = ApprovalInstance::query()
            ->pendingForApprover($userId, $identity['roles'], $identity['positionId'])
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $actedIds = ApprovalInstanceStep::query()
            ->where('acted_by_user_id', $userId)
            ->orderByDesc('approval_instance_id')
            ->pluck('approval_instance_id')
            ->unique()
            ->reject(fn ($id) => in_array($id, $pendingIds, true))
            ->values()
            ->all();

        $instances = ApprovalInstance::query()
            ->with(['steps', 'workflow', 'requester', 'formSubmission'])
            ->whereKey(array_merge($pendingIds, $actedIds))
            ->get()
            ->keyBy('id');

        $pending = collect($pendingIds)->map(fn ($id) => $instances->get($id))->filter()->values();
        $acted = collect($actedIds)->map(fn ($id) => $instances->get($id))->filter()->values();

        $grouped = $pending->groupBy('document_type');
        $actedGrouped = $acted->groupBy('document_type');

        return view('approvals.my-approvals', compact('grouped', 'actedGrouped'));
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

        // Validate required_at_step fields before allowing approve
        if ($action === 'approved') {
            $submission = $instance->formSubmission;
            if ($submission) {
                $stepNo = $instance->current_step_no;
                $payload = (array) ($submission->payload ?? []);
                $missing = $submission->form->fields
                    ->filter(fn ($f) => in_array($stepNo, $f->required_at_step ?? []))
                    ->filter(fn ($f) => ($payload[$f->field_key] ?? '') === '' || $payload[$f->field_key] === null)
                    ->map(fn ($f) => $f->localized_label);
                if ($missing->isNotEmpty()) {
                    return back()->withErrors(['approve' => __('common.approver_fields_required', ['fields' => $missing->join(', ')])]);
                }
            }
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

        // Sidebar badge caches the pending count 60s — bust it so the actor's
        // count drops immediately after approve/reject instead of lagging.
        Cache::forget("pending_approvals_count:{$userId}");

        // Log approval/rejection to submission activity trail (only on success)
        $submissionId = $instance->formSubmission?->id;
        if ($submissionId) {
            $meta = [];
            if (! empty($validated['comment'])) {
                $meta['comment'] = $validated['comment'];
            }
            SubmissionActivityLog::record($submissionId, $userId, $action, $meta);
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

        // Dynamic-form submissions store field values in document_form_submissions
        // (+ fdata_*), NOT in approval_instances.payload. Resolve the linked
        // submission so we (a) pick the EXACT form even when several forms share a
        // document_type, and (b) write back to where the view actually reads from.
        $submission = DocumentFormSubmission::where('approval_instance_id', $instance->id)->first();

        $form = $submission
            ? $submission->form()->with('fields')->first()
            : DocumentForm::query()
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

        if ($submission) {
            // Dynamic form: dual-write submission.payload + fdata_* (mirrors
            // DocumentFormSubmissionController::updateDraft) so the change shows
            // on the submission view and stays consistent with the dedicated table.
            $payload = $submission->payload ?? [];
            foreach ($safeUpdates as $key => $value) {
                $payload[$key] = $value;
            }
            $payload = \App\Support\FormulaFields::recompute($form, $payload);

            $changedFields = PayloadDiffer::diff(
                $submission->payload ?? [],
                $payload,
                $form->fields->keyBy('field_key')->all(),
            );

            $submission->update(['payload' => $payload]);

            if ($submission->fdata_row_id && $form->hasDedicatedTable()) {
                $this->schemaService->updateRow($form, $submission->fdata_row_id, $payload);
            }

            SubmissionActivityLog::record(
                $submission->id,
                $userId,
                'updated',
                $changedFields ? ['changed_fields' => $changedFields] : [],
            );
        } else {
            // Legacy form (purchase_request): values live in the
            // instance payload itself.
            $payload = $instance->payload ?? [];
            foreach ($safeUpdates as $key => $value) {
                $payload[$key] = $value;
            }
            $payload = \App\Support\FormulaFields::recompute($form, $payload);
            $instance->update(['payload' => $payload]);
        }

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
