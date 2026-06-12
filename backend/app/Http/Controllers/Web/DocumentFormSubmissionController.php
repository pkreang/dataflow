<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\Setting;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use App\Models\UserSubstitution;
use App\Services\ApprovalFlowService;
use App\Services\ApproverIdentity;
use App\Services\FormSchemaService;
use App\Support\DateExpressionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DocumentFormSubmissionController extends Controller
{
    use HasPerPage;

    public function __construct(
        private readonly FormSchemaService $schemaService,
        private readonly ApprovalFlowService $approvalFlow,
    ) {}

    public function index(): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;

        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->where('document_type', '!=', 'evaluation') // eval forms triggered via parent submission only
            ->visibleToUser($userDeptId)
            ->orderBy('name')
            ->get()
            ->groupBy('document_type');

        return view('forms.index', compact('forms'));
    }

    public function mySubmissions(): View
    {
        $userId = (int) (session('user.id') ?? 0);

        // Owner sees their own submissions; assigned editors also see drafts
        // they were granted edit access to so they can find and complete them.
        $submissions = DocumentFormSubmission::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereJsonContains('assigned_editor_user_ids', $userId);
            })
            ->with(['form', 'instance'])
            ->latest()
            ->get()
            ->groupBy(fn ($s) => $s->form?->name ?? '—');

        return view('forms.my-submissions', compact('submissions'));
    }

    public function listByForm(DocumentForm $documentForm, Request $request, ApproverIdentity $approverIdentity): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;
        $isSuperAdmin = (bool) session('user.is_super_admin', false);
        $identity = $approverIdentity->fromSession();

        abort_if(! $documentForm->is_active, 404);
        // Super-admins bypass the department-scope check so they can monitor /
        // support submissions on every form regardless of their own department.
        abort_unless(
            $isSuperAdmin
                || DocumentForm::query()->whereKey($documentForm->id)->visibleToUser($userDeptId)->exists(),
            404
        );

        // Drop searchable columns the viewer can't see by department, mirroring
        // the field-level visibility in dynamic-field.blade.php. Without this the
        // approver-pending rows below would surface restricted searchable values
        // in the list columns (the list renderer doesn't honor visible_to_departments).
        $searchable = $documentForm->fields()
            ->where('is_searchable', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($field) => $isSuperAdmin || $this->fieldVisibleToDept($field, $userDeptId))
            ->values();
        $filters = $this->extractFilters($request, $searchable);

        // Instances of this form related to the viewer: (a) currently awaiting
        // their approval, PLUS (b) ones they have already acted on (approved /
        // rejected any step) — so an approver keeps sight of requests they
        // handled, not only the ones still pending. Two cheap queries (a user's
        // pending + acted sets are tiny), merged and fed into the main query via
        // whereIn so the paginated query stays single and counts stay correct.
        $relatedInstanceIds = $isSuperAdmin
            ? []
            : array_values(array_unique(array_merge(
                ApprovalInstance::query()
                    ->pendingForApprover($identity['userId'], $identity['roles'], $identity['positionId'])
                    ->pluck('id')
                    ->all(),
                // Query the steps table directly (not whereHas) to dodge the
                // larastan custom-relation-return-type false positive.
                ApprovalInstanceStep::query()
                    ->where('acted_by_user_id', $identity['userId'])
                    ->pluck('approval_instance_id')
                    ->all()
            )));

        $showCancelled = (bool) $request->query('show_cancelled');
        $query = DocumentFormSubmission::query()
            ->when($showCancelled, fn ($q) => $q->withTrashed())
            ->where('document_form_submissions.form_id', $documentForm->id)
            // Super-admins see every submission for the form (monitoring/support).
            // Everyone else is scoped to their own submissions, drafts where they
            // were granted edit access (assigned_editor_user_ids), plus any
            // submission of this form awaiting their approval OR one they already
            // acted on — so approvers find both pending work and their handled
            // requests in the form's own list, not only the /approvals/my inbox.
            ->when(! $isSuperAdmin, function ($q) use ($userId, $relatedInstanceIds) {
                $q->where(function ($inner) use ($userId, $relatedInstanceIds) {
                    $inner->where('document_form_submissions.user_id', $userId)
                        ->orWhereJsonContains('document_form_submissions.assigned_editor_user_ids', $userId);
                    if (! empty($relatedInstanceIds)) {
                        $inner->orWhereIn('document_form_submissions.approval_instance_id', $relatedInstanceIds);
                    }
                });
            });

        $referenceNoFilter = trim((string) $request->query('reference_no', ''));
        if ($referenceNoFilter !== '') {
            $query->whereRaw('LOWER(document_form_submissions.reference_no) LIKE ?', [
                '%'.mb_strtolower($referenceNoFilter).'%',
            ]);
            $filters['reference_no'] = $referenceNoFilter;
        }

        $this->applyFieldFilters($query, $documentForm, $searchable, $filters);

        $perPage = $this->resolvePerPage($request, 'list_by_form_per_page');
        $with = ['instance', 'latestActivity.user', 'submittedActivity'];
        if ($documentForm->document_type === 'evaluation') {
            $with[] = 'originalSubmission.form';
        }
        $submissions = $query->select('document_form_submissions.*')
            ->with($with)
            ->latest('document_form_submissions.id')
            ->paginate($perPage)
            ->withQueryString();

        return view('forms.list-by-form', [
            'form' => $documentForm,
            'submissions' => $submissions,
            'searchable' => $searchable,
            'filters' => $filters,
            'showCancelled' => $showCancelled,
            'perPage' => $perPage,
            'relatedInstanceIds' => $relatedInstanceIds,
        ]);
    }

    /**
     * Pull filter values from the request keyed by field_key. Date/datetime fields
     * read `{key}_from` and `{key}_to`; everything else reads the key directly.
     */
    private function extractFilters(Request $request, Collection $searchable): array
    {
        $filters = [];
        foreach ($searchable as $field) {
            $key = $field->field_key;
            if (in_array($field->field_type, ['date', 'datetime'], true)) {
                $from = $request->query($key.'_from');
                $to = $request->query($key.'_to');
                if (filled($from)) {
                    $filters[$key.'_from'] = (string) $from;
                }
                if (filled($to)) {
                    $filters[$key.'_to'] = (string) $to;
                }
            } else {
                $val = $request->query($key);
                if (filled($val)) {
                    $filters[$key] = is_array($val) ? $val : (string) $val;
                }
            }
        }

        return $filters;
    }

    private function applyFieldFilters(Builder $query, DocumentForm $form, Collection $searchable, array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        $hasFdata = $form->hasDedicatedTable();
        if ($hasFdata) {
            $query->leftJoin($form->submission_table.' as ft', 'ft.id', '=', 'document_form_submissions.fdata_row_id');
        }

        foreach ($searchable as $field) {
            $key = $field->field_key;
            $type = $field->field_type;

            if (in_array($type, ['date', 'datetime'], true)) {
                $from = $filters[$key.'_from'] ?? null;
                $to = $filters[$key.'_to'] ?? null;
                if ($from === null && $to === null) {
                    continue;
                }
                if ($hasFdata) {
                    $col = 'ft.'.$key;
                    if ($from !== null) {
                        $query->where($col, '>=', $from);
                    }
                    if ($to !== null) {
                        $query->where($col, '<=', $to);
                    }
                } else {
                    $expr = "json_extract(document_form_submissions.payload, '$.".$key."')";
                    if ($from !== null) {
                        $query->whereRaw("$expr >= ?", [$from]);
                    }
                    if ($to !== null) {
                        $query->whereRaw("$expr <= ?", [$to]);
                    }
                }

                continue;
            }

            if (! array_key_exists($key, $filters)) {
                continue;
            }
            $val = $filters[$key];

            if ($hasFdata) {
                $col = 'ft.'.$key;
                match ($type) {
                    'select', 'radio', 'lookup', 'number', 'email', 'phone' => $query->where($col, (string) $val),
                    default => $query->whereRaw('LOWER('.$col.') LIKE ?', ['%'.mb_strtolower((string) $val).'%']),
                };
            } else {
                $expr = "json_extract(document_form_submissions.payload, '$.".$key."')";
                match ($type) {
                    'select', 'radio', 'lookup', 'number', 'email', 'phone' => $query->whereRaw("$expr = ?", [(string) $val]),
                    default => $query->whereRaw("LOWER($expr) LIKE ?", ['%'.mb_strtolower((string) $val).'%']),
                };
            }
        }
    }

    public function create(DocumentForm $documentForm): View
    {
        abort_if(! $documentForm->is_active, 404);
        // Evaluation forms must be filled via the "Evaluate" button on an approved
        // parent submission — not by directly navigating to forms.create.
        abort_if($documentForm->document_type === 'evaluation', 403,
            'Evaluation forms can only be filled via the "Evaluate" button on an approved submission.');
        $documentForm->load('fields');

        // "Submit on behalf of" — permission-gated picker of active users.
        $onBehalfUsers = $this->canCreateForOthers()
            ? User::query()->where('is_active', true)
                ->where('id', '!=', (int) (session('user.id') ?? 0))
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
            : collect();

        return view('forms.create', ['form' => $documentForm, 'onBehalfUsers' => $onBehalfUsers]);
    }

    public function storeDraft(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        abort_if(! $documentForm->is_active, 404);
        abort_if($documentForm->document_type === 'evaluation', 403,
            'Evaluation forms must be submitted through the "Evaluate" button on a parent submission.');
        $documentForm->load('fields');

        $spec = $this->buildPayloadRules($documentForm, (array) $request->input('fields', []));
        $validated = $request->validate($spec['rules'], [], $spec['attributes']);
        $payload = $validated['fields'] ?? [];

        $payload = $this->processFileUploads($documentForm, $payload, $request);
        $payload = $this->recomputeFormulaFields($documentForm, $payload);

        $userId = (int) (session('user.id') ?? 0);

        // "Submit on behalf of": the document is OWNED by the beneficiary;
        // the logged-in creator is recorded in created_by_user_id and kept as
        // an assigned editor so they retain draft access. Permission-gated
        // server-side — without it the parameter is silently ignored.
        $ownerId = $userId;
        $createdById = null;
        $onBehalfId = (int) $request->input('on_behalf_of_user_id', 0);
        if ($onBehalfId && $onBehalfId !== $userId && $this->canCreateForOthers()) {
            $beneficiary = User::query()->where('is_active', true)->find($onBehalfId);
            abort_unless($beneficiary !== null, 422);
            $ownerId = $beneficiary->id;
            $createdById = $userId;
        }

        $userDeptId = $createdById
            ? User::find($ownerId)?->department_id
            : (session('user.department_id') ?? User::find($userId)?->department_id);

        $submission = DocumentFormSubmission::create([
            'form_id' => $documentForm->id,
            'user_id' => $ownerId,
            'created_by_user_id' => $createdById,
            'department_id' => $userDeptId,
            'payload' => $payload,
            'status' => 'draft',
            'assigned_editor_user_ids' => $createdById ? [$createdById] : null,
        ]);

        // Dual-write: insert into fdata_* table
        $fdataRowId = $this->writeFdataRow($documentForm, $payload, [
            'user_id' => $ownerId,
            'department_id' => $userDeptId,
            'status' => 'draft',
        ]);

        if ($fdataRowId) {
            $submission->update(['fdata_row_id' => $fdataRowId]);
        }

        SubmissionActivityLog::record($submission->id, $userId, 'created');

        if ($request->input('_intent') === 'submit') {
            return redirect()->route('forms.draft.edit', $submission)->with('autosubmit', true);
        }

        return redirect()->route('forms.draft.edit', $submission)->with('success', __('common.saved'));
    }

    public function editDraft(DocumentFormSubmission $submission): View
    {
        $this->authorizeOwnerDraft($submission);
        $submission->load('form.fields');

        // override: if the admin feature is on AND the resolved workflow has stages
        // with allow_requester_override=true, surface those stages + eligible approvers
        // so the submit form can render an optional substitute-approver picker.
        $overrideStages = collect();
        $eligibleApprovers = collect();
        if (Setting::getBool('approval.allow_requester_override', false)) {
            $form = $submission->form()->first();
            $workflow = $form ? $this->approvalFlow->previewWorkflow(
                $form->document_type,
                $submission->department_id,
                (int) (session('user.id') ?? 0),
                $form->form_key,
            ) : null;
            $overrideStages = $workflow
                ? ApprovalWorkflowStage::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('is_active', true)
                    ->where('allow_requester_override', true)
                    ->orderBy('step_no')
                    ->get()
                : collect();
            if ($overrideStages->isNotEmpty()) {
                $eligibleApprovers = User::permission('approval.approve')
                    ->orderBy('first_name')
                    ->get()
                    ->map(fn (User $u) => [
                        'id' => $u->id,
                        'label' => trim($u->first_name.' '.$u->last_name).' ('.$u->email.')',
                    ])
                    ->values();
            }
        }

        return view('forms.edit-draft', compact('submission', 'overrideStages', 'eligibleApprovers'));
    }

    public function updateDraft(Request $request, DocumentFormSubmission $submission): RedirectResponse
    {
        $this->authorizeOwnerDraft($submission);
        $submission->load('form.fields');

        $spec = $this->buildPayloadRules($submission->form, (array) $request->input('fields', []));
        $validated = $request->validate($spec['rules'], [], $spec['attributes']);
        $payload = $validated['fields'] ?? [];

        $payload = $this->processFileUploads(
            $submission->form,
            $payload,
            $request,
            existingPayload: $submission->payload ?? []
        );

        // Non-owners (assigned editors) only get to write fields whose
        // editable_by tokens grant them access via 'user:{id}'. Owner keeps
        // full write across all fields tagged 'requester' (or any token);
        // the on-behalf creator counts as owner for field editing.
        $userId = (int) (session('user.id') ?? 0);
        $isOwner = (int) $submission->user_id === $userId || $submission->isCreator($userId);
        if (! $isOwner) {
            $allowed = $this->filterPayloadForAssignee($submission, $payload, $userId);
            $payload = array_merge($submission->payload ?? [], $allowed);
        }

        // Recompute formula fields server-side — never trust the client value
        // since the hidden mirror input is editable via devtools.
        $payload = $this->recomputeFormulaFields($submission->form, $payload);

        // Capture per-field diff BEFORE the update so we can record what changed.
        // Computed against the post-filter payload (what's actually persisted),
        // so audit accurately reflects DB state — not what was attempted.
        $fieldDefsByKey = $submission->form->fields->keyBy('field_key')->all();
        $changedFields = \App\Support\PayloadDiffer::diff(
            $submission->payload ?? [],
            $payload,
            $fieldDefsByKey,
        );

        $submission->update(['payload' => $payload]);

        // Dual-write: update fdata_* row
        if ($submission->fdata_row_id && $submission->form->hasDedicatedTable()) {
            $this->schemaService->updateRow($submission->form, $submission->fdata_row_id, $payload);
        }

        SubmissionActivityLog::record(
            $submission->id,
            (int) session('user.id'),
            'updated',
            $changedFields ? ['changed_fields' => $changedFields] : [],
        );

        return redirect()->route('forms.draft.edit', $submission)->with('success', __('common.saved'));
    }

    public function destroyDraft(DocumentFormSubmission $submission): RedirectResponse
    {
        $this->authorizeOwnerOnlyDraft($submission);

        // Dual-write: delete fdata_* row
        if ($submission->fdata_row_id && $submission->form->hasDedicatedTable()) {
            $this->schemaService->deleteRow($submission->form, $submission->fdata_row_id);
        }

        SubmissionActivityLog::record($submission->id, (int) session('user.id'), 'cancelled', ['reference_no' => $submission->reference_no]);
        $submission->delete();

        return redirect()->route('forms.my-submissions')->with('success', __('common.deleted'));
    }

    /**
     * Super-admin-only recovery of a cancelled submission. Rebuilds the
     * fdata_* row that was hard-deleted during cancellation so reports and
     * list queries see the row again.
     *
     * The route parameter is a plain string (not bound to the model) so the
     * default soft-delete global scope can't filter it out before we call
     * `withTrashed()`.
     */
    public function restore(string $submission): RedirectResponse
    {
        abort_unless(session('user.is_super_admin', false), 403);

        $trashed = DocumentFormSubmission::withTrashed()->findOrFail((int) $submission);
        if (! $trashed->trashed()) {
            return redirect()->route('forms.submission.show', $trashed);
        }

        $userId = (int) (session('user.id') ?? 0);

        $trashed->restore();
        $trashed->forceFill(['deleted_by' => null])->saveQuietly();

        $trashed->load('form');
        $form = $trashed->form;
        if ($form?->hasDedicatedTable()) {
            $newId = $this->schemaService->insertRow($form, $trashed->payload ?? [], [
                'user_id' => $trashed->user_id,
                'department_id' => $trashed->department_id,
                'status' => $trashed->status,
                'reference_no' => $trashed->reference_no,
                'approval_instance_id' => $trashed->approval_instance_id,
            ]);
            if ($newId) {
                $trashed->forceFill(['fdata_row_id' => $newId])->saveQuietly();
            }
        }

        SubmissionActivityLog::record($trashed->id, $userId, 'restored', [
            'reference_no' => $trashed->reference_no,
        ]);

        return redirect()->route('forms.submission.show', $trashed)
            ->with('success', __('common.restored'));
    }

    /**
     * Rejected → draft: lets the owner re-edit and resubmit the same submission
     * without losing reference_no or approval history.
     *
     * We keep `approval_instance_id` linked so the rejection trail stays visible
     * on the edit page; `submit()` overwrites the reference_no and instance id
     * when the owner resubmits, so no cleanup is needed here.
     */
    public function returnToDraft(DocumentFormSubmission $submission): RedirectResponse
    {
        $this->authorizeReturnToDraft($submission);

        $userId = (int) (session('user.id') ?? 0);
        $fromInstanceId = $submission->approval_instance_id;

        $this->applyReturnToDraft($submission);

        SubmissionActivityLog::record($submission->id, $userId, 'returned_to_draft', [
            'from_approval_instance_id' => $fromInstanceId,
        ]);

        return redirect()->route('forms.draft.edit', $submission)
            ->with('success', __('common.returned_to_draft'));
    }

    /**
     * Approver action — send a submitted request back instead of approving or
     * rejecting it. `requester` flips the submission to an editable draft;
     * `previous_step` rewinds the workflow one approval stage. The workflow
     * mutation and actor authorization happen inside ApprovalFlowService.
     */
    public function sendBack(Request $request, DocumentFormSubmission $submission, ApprovalFlowService $approvalFlowService): RedirectResponse
    {
        $validated = $request->validate([
            'destination' => 'required|in:requester,previous_step',
            'comment' => 'required|string|max:1000',
        ]);

        abort_unless($submission->approval_instance_id, 404);

        $userId = (int) (session('user.id') ?? 0);

        try {
            $approvalFlowService->sendBack(
                $submission->approval_instance_id,
                $userId,
                $validated['destination'],
                $validated['comment'],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['send_back' => $e->getMessage()]);
        }

        if ($validated['destination'] === 'requester') {
            $this->applyReturnToDraft($submission);
        }

        SubmissionActivityLog::record($submission->id, $userId, 'sent_back', [
            'destination' => $validated['destination'],
            'comment' => $validated['comment'],
        ]);

        return redirect()->route('approvals.my')
            ->with('success', __('common.send_back_success'));
    }

    /**
     * Flip a submission back to an editable draft, mirroring the change into
     * the dedicated fdata_* row when the form uses one. Shared by
     * returnToDraft() (owner action) and sendBack() (approver action).
     */
    private function applyReturnToDraft(DocumentFormSubmission $submission): void
    {
        $submission->loadMissing('form');
        $submission->update(['status' => 'draft']);

        if ($submission->fdata_row_id && $submission->form?->hasDedicatedTable()) {
            $this->schemaService->updateRow($submission->form, $submission->fdata_row_id, $submission->payload ?? [], [
                'status' => 'draft',
            ]);
        }
    }

    public function submit(DocumentFormSubmission $submission, Request $request, ApprovalFlowService $approvalFlowService, \App\Services\LeaveValidationService $leaveValidator): RedirectResponse
    {
        $this->authorizeOwnerOnlyDraft($submission);
        $submission->load('form');

        $form = $submission->form;
        $userId = (int) (session('user.id') ?? 0);

        // On-behalf: the workflow requester is the document OWNER, not whoever
        // pressed submit — routing (direct_manager/org_head), requester
        // exclusion and overlap checks all follow the owner.
        $ownerId = (int) $submission->user_id;

        // requester_pick: map[step_no => chosen user_id]. start() validates that
        // the chosen user actually holds approval.approve before trusting it.
        $pickedApprovers = collect($request->input('picked_approvers', []))
            ->mapWithKeys(fn ($uid, $stepNo) => [(int) $stepNo => (int) $uid])
            ->all();

        // Extract amount for amount-based routing if policy configures an amount_field_key
        $payload = (array) ($submission->payload ?? []);
        $amount = null;
        $amountPolicy = \App\Models\DocumentFormWorkflowPolicy::query()
            ->where('form_id', $submission->form_id)
            ->where('use_amount_condition', true)
            ->whereNotNull('amount_field_key')
            ->first();
        if ($amountPolicy !== null && $amountPolicy->amount_field_key !== null) {
            $rawAmount = $payload[$amountPolicy->amount_field_key] ?? null;
            if (is_numeric($rawAmount)) {
                $amount = (float) $rawAmount;
            }
        }

        // Leave overlap guard: runs for any form that stores date_from / date_to
        // (currently only leave_request forms). No-op for all other form types.
        if (isset($payload['date_from'], $payload['date_to'])) {
            try {
                $leaveValidator->checkOverlap(
                    userId: $ownerId,
                    formId: $form->id,
                    dateFrom: (string) $payload['date_from'],
                    dateTo: (string) $payload['date_to'],
                    excludeId: $submission->id,
                );
            } catch (\RuntimeException $e) {
                return redirect()->back()->withErrors([
                    'submit' => __('common.'.$e->getMessage()),
                ]);
            }
        }

        try {
            $positionId = (int) (User::find($ownerId)?->position_id ?? 0);
            $instance = $approvalFlowService->start(
                documentType: $form->document_type,
                departmentId: $submission->department_id,
                requesterUserId: $ownerId,
                referenceNo: null,
                payload: $payload,
                formKey: $form->form_key,
                amount: $amount,
                pickedApprovers: $pickedApprovers,
                positionId: $positionId,
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage() === 'requester_pick_invalid_approver'
                ? __('common.requester_pick_invalid_approver')
                : $e->getMessage();

            return redirect()->back()->withErrors(['submit' => $message]);
        }

        $submission->update([
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        // Dual-write: update fdata_* row with submission metadata
        if ($submission->fdata_row_id && $form->hasDedicatedTable()) {
            $this->schemaService->updateRow($form, $submission->fdata_row_id, $submission->payload ?? [], [
                'status' => 'submitted',
                'reference_no' => $instance->reference_no,
                'approval_instance_id' => $instance->id,
            ]);
        }

        SubmissionActivityLog::record($submission->id, $userId, 'submitted', ['reference_no' => $instance->reference_no]);

        return redirect()->route('forms.submission.show', $submission)->with('success', __('common.saved'));
    }

    public function showSubmission(DocumentFormSubmission $submission): View
    {
        $this->authorizeView($submission);

        $submission->load(['form.fields', 'instance.steps', 'instance.workflow', 'department']);
        $activity = SubmissionActivityLog::with('user')
            ->where('submission_id', $submission->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        $userId = (int) (session('user.id') ?? 0);
        $isOwner = (int) $submission->user_id === $userId;
        $isSuperAdmin = (bool) session('user.is_super_admin', false);

        // Approver inline-edit: when this viewer is the current pending approver,
        // resolve their step token so fields tagged editable_by=['step_N'] (or
        // 'user:{id}') render editable on the submission view. Everyone else —
        // owner, prior/next-step approvers, non-pending — gets 'view_only'
        // (read-only, no regression). The PATCH route re-filters server-side.
        $editorRole = $this->resolveEditorRole($submission, $userId);
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;
        $editorUserId = $userId ?: null;

        // Whether this viewer may act (approve/reject) on the document right now.
        // Distinct from $editorRole (field-edit): $canAct ALSO requires the
        // approval.approve permission so the action card never renders for a user
        // whose POST to approvals.act would 403. Mirrors RepairRequestController::show.
        $canAct = false;
        $instance = $submission->instance;
        if ($instance && $instance->status === 'pending'
            && in_array('approval.approve', session('user_permissions', []), true)) {
            $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);
            if ($currentStep && $currentStep->action === 'pending') {
                $canAct = $this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId);
            }
        }

        // Only the owner / super-admin can manage assigned editors, and only
        // while the submission is still a draft (post-submit, the workflow
        // owns who-can-edit).
        $canManageAssignedEditors = ($isOwner || $isSuperAdmin) && $submission->status === 'draft';

        $assignedEditorRows = [];
        $assignableUsers = [];
        if ($canManageAssignedEditors || ! empty($submission->assigned_editor_user_ids)) {
            $ids = array_map('intval', $submission->assigned_editor_user_ids ?? []);
            if ($ids) {
                $assignedEditorRows = User::query()
                    ->whereIn('id', $ids)
                    ->get(['id', 'first_name', 'last_name'])
                    ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();
            }
        }
        if ($canManageAssignedEditors) {
            $assignableUsers = User::query()
                ->where('is_active', true)
                ->where('id', '!=', $submission->user_id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();
        }

        return view('forms.show-submission', compact(
            'submission',
            'activity',
            'canManageAssignedEditors',
            'assignedEditorRows',
            'assignableUsers',
            'editorRole',
            'userDeptId',
            'editorUserId',
            'canAct',
        ));
    }

    /**
     * Approver edit-context resolver: returns 'step_N' when the viewer is the
     * approver currently able to act on this submission's pending instance, else
     * 'view_only'. Mirrors PurchaseRequestController::resolveEditorRole so the
     * dynamic-form view honors the same field-level editable_by step tokens.
     */
    private function resolveEditorRole(DocumentFormSubmission $submission, int $userId): string
    {
        $instance = $submission->instance;
        if (! $instance || $instance->status !== 'pending') {
            return 'view_only';
        }

        $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);
        if ($currentStep
            && $currentStep->action === 'pending'
            && $this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId)) {
            return 'step_'.$instance->current_step_no;
        }

        return 'view_only';
    }

    /**
     * Dedicated audit view: full activity history (no 20-row cap) for a
     * single submission. Same authorization as show — anyone who can see
     * the submission can see its audit trail.
     */
    public function history(DocumentFormSubmission $submission): View
    {
        $this->authorizeView($submission);

        $submission->load(['form', 'instance.workflow']);
        $activities = SubmissionActivityLog::with('user')
            ->where('submission_id', $submission->id)
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('forms.submission-history', compact('submission', 'activities'));
    }

    public function print(DocumentFormSubmission $submission): View
    {
        $this->authorizeView($submission);
        abort_if($submission->status === 'draft', 404);

        $submission->load(['form.fields', 'instance.steps.approver', 'instance.workflow', 'department', 'user']);

        SubmissionActivityLog::record($submission->id, (int) session('user.id'), 'printed');

        return view('forms.print-submission', compact('submission'));
    }

    public function downloadPdf(DocumentFormSubmission $submission): HttpResponse
    {
        $this->authorizeView($submission);
        abort_if($submission->status === 'draft', 404);

        $submission->load(['form.fields', 'instance.steps', 'department', 'user']);

        $pdf = Pdf::loadView('pdf.submission', compact('submission'))
            ->setPaper('a4', 'portrait')
            ->setWarnings(false);

        $filename = ($submission->reference_no
            ? preg_replace('/[^A-Za-z0-9\-_]/', '-', $submission->reference_no)
            : 'submission-'.$submission->id).'.pdf';

        SubmissionActivityLog::record($submission->id, (int) session('user.id'), 'printed');

        return $pdf->download($filename);
    }

    /**
     * Bulk delete — only processes submissions owned by the current user and still in
     * draft state. Silently skips anything else so URL-tampered IDs can't cause harm.
     */
    public function bulkDeleteDrafts(Request $request): RedirectResponse
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');
        if (empty($ids)) {
            return back();
        }
        $userId = (int) (session('user.id') ?? 0);

        $submissions = DocumentFormSubmission::whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->with('form')
            ->get();

        foreach ($submissions as $submission) {
            if ($submission->fdata_row_id && $submission->form?->hasDedicatedTable()) {
                $this->schemaService->deleteRow($submission->form, $submission->fdata_row_id);
            }
            SubmissionActivityLog::record($submission->id, $userId, 'cancelled', ['reference_no' => $submission->reference_no, 'bulk' => true]);
            $submission->delete();
        }

        return back()->with('success', __('common.bulk_deleted', ['count' => $submissions->count()]));
    }

    /**
     * Replace the list of assigned editors on a submission. Only the owner
     * (or super-admin) may change this list. Submitted user_ids are validated
     * against active users; the owner cannot self-assign (they already have
     * full access by virtue of ownership). Status must be 'draft' — assigning
     * editors after submission is meaningless because no one can edit a
     * submitted form except via the approval flow.
     */
    public function updateAssignedEditors(Request $request, DocumentFormSubmission $submission): RedirectResponse
    {
        $userId = (int) (session('user.id') ?? 0);
        $isOwner = (int) $submission->user_id === $userId;
        $isSuperAdmin = (bool) session('user.is_super_admin', false);
        abort_unless($isOwner || $isSuperAdmin, 403);
        abort_unless($submission->status === 'draft', 422);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);
        $rawIds = $validated['user_ids'] ?? [];

        $cleanIds = array_values(array_unique(array_map('intval', $rawIds)));
        $cleanIds = array_values(array_filter($cleanIds, fn ($id) => $id > 0 && $id !== (int) $submission->user_id));

        $validIds = $cleanIds
            ? User::query()->whereIn('id', $cleanIds)->where('is_active', true)->pluck('id')->all()
            : [];
        $validIds = array_values(array_map('intval', $validIds));

        $previous = $submission->assigned_editor_user_ids ?? [];
        $submission->update([
            'assigned_editor_user_ids' => $validIds ?: null,
        ]);

        SubmissionActivityLog::record($submission->id, $userId, 'assigned_editors_changed', [
            'previous' => array_values(array_map('intval', $previous)),
            'current' => $validIds,
        ]);

        return redirect()->route('forms.submission.show', $submission)
            ->with('success', __('common.assigned_editors_updated'));
    }

    public function duplicate(DocumentFormSubmission $submission): RedirectResponse
    {
        $userId = (int) (session('user.id') ?? 0);
        abort_unless((int) $submission->user_id === $userId, 403);

        $submission->load('form');
        $form = $submission->form;
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;

        $copy = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $userId,
            'department_id' => $userDeptId,
            'payload' => $submission->payload ?? [],
            'status' => 'draft',
            'reference_no' => null,
            'approval_instance_id' => null,
            'fdata_row_id' => null,
        ]);

        if ($form->hasDedicatedTable()) {
            $rowId = $this->schemaService->insertRow($form, $submission->payload ?? [], [
                'user_id' => $userId,
                'department_id' => $userDeptId,
                'status' => 'draft',
            ]);
            if ($rowId) {
                $copy->update(['fdata_row_id' => $rowId]);
            }
        }

        SubmissionActivityLog::record($copy->id, $userId, 'duplicated', [
            'source_submission_id' => $submission->id,
            'source_reference_no' => $submission->reference_no,
        ]);

        return redirect()
            ->route('forms.draft.edit', $copy)
            ->with('success', __('common.action_duplicate_success'));
    }

    // ── Private helpers ─────────────────────────────────────

    /**
     * Edit-draft gate: owner OR an assigned editor may write field values to
     * a draft. Status must be 'draft' — assignees cannot edit submitted /
     * approved forms through this gate (approvers use a separate path).
     */
    private function authorizeOwnerDraft(DocumentFormSubmission $submission): void
    {
        $userId = (int) (session('user.id') ?? 0);
        $isOwner = (int) $submission->user_id === $userId;
        $isAssignee = $submission->isAssignedEditor($userId);
        abort_unless($isOwner || $isAssignee, 403);
        abort_unless($submission->status === 'draft', 403);
    }

    /** Holder of submission.create_for_others (or super-admin) may file on behalf. */
    private function canCreateForOthers(): bool
    {
        return (bool) session('user.is_super_admin', false)
            || in_array('submission.create_for_others', session('user_permissions', []), true);
    }

    /**
     * Lifecycle-changing actions (submit, destroy, return-to-draft) stay
     * owner-only — assignees collaborate on content, not workflow state.
     * Submitting as an assignee would also confuse downstream "requester"
     * lookups in the approval flow. Exception: the on-behalf CREATOR filed
     * the document and may drive its lifecycle (the workflow still treats
     * the owner as requester).
     */
    private function authorizeOwnerOnlyDraft(DocumentFormSubmission $submission): void
    {
        $userId = (int) (session('user.id') ?? 0);
        abort_unless((int) $submission->user_id === $userId || $submission->isCreator($userId), 403);
        abort_unless($submission->status === 'draft', 403);
    }

    /**
     * Pulling a rejected submission back to draft is owner-only — see
     * authorizeOwnerOnlyDraft rationale (it changes lifecycle).
     */
    private function authorizeReturnToDraft(DocumentFormSubmission $submission): void
    {
        $userId = (int) (session('user.id') ?? 0);
        abort_unless((int) $submission->user_id === $userId || $submission->isCreator($userId), 403);
        abort_unless($submission->effective_status === 'rejected', 403);
    }

    private function authorizeView(DocumentFormSubmission $submission): void
    {
        $userId = (int) (session('user.id') ?? 0);
        $isOwner = (int) $submission->user_id === $userId || $submission->isCreator($userId);
        $isAssignee = $submission->isAssignedEditor($userId);
        $isSuperAdmin = (bool) session('user.is_super_admin', false);
        if ($isOwner || $isAssignee || $isSuperAdmin) {
            return;
        }
        abort_unless($this->isApproverForSubmission($submission, $userId), 403);
    }

    /**
     * Restrict a draft-update payload to the field keys the given non-owner
     * editor is allowed to write, based on each field's editable_by tokens.
     * Only fields whose tokens include 'user:{$userId}' are kept; everything
     * else is dropped server-side so a tampered request can't bypass the UI.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterPayloadForAssignee(DocumentFormSubmission $submission, array $payload, int $userId): array
    {
        $token = 'user:'.$userId;
        $allowedKeys = $submission->form?->fields
            ?->filter(fn (DocumentFormField $f) => in_array($token, $f->effective_editable_by, true))
            ->pluck('field_key')
            ->all() ?? [];

        return array_intersect_key($payload, array_flip($allowedKeys));
    }

    /**
     * Approver scope: the user is listed as an approver on ANY step of this submission's
     * approval instance — past, current, or future. This is stricter than the legacy
     * blanket `approval.approve` permission (which exposed every pending submission to
     * every approver) while still letting auditors who handled the doc look back at it.
     */
    /**
     * Department-based field visibility — same predicate as dynamic-field.blade.php:
     * a field with no visible_to_departments is visible to all; otherwise only to
     * users whose department is listed. Used to drop restricted columns from the
     * searchable list before rendering.
     */
    private function fieldVisibleToDept(DocumentFormField $field, int|string|null $userDeptId): bool
    {
        $depts = $field->visible_to_departments;
        if (empty($depts)) {
            return true;
        }

        return $userDeptId !== null
            && in_array((int) $userDeptId, array_map('intval', $depts), true);
    }

    private function isApproverForSubmission(DocumentFormSubmission $submission, int $userId): bool
    {
        $instance = $submission->instance;
        if (! $instance) {
            return false;
        }

        // รวม principal ที่ user นี้เป็น active substitute ให้ — ให้เห็นเอกสาร
        // ที่ principal เป็น approver ได้ (สอดคล้อง scopePendingForApprover)
        $userRefs = array_map('strval', array_merge(
            [$userId],
            UserSubstitution::activePrincipalsFor($userId, now())
        ));
        $roleNames = collect(session('user.roles', []))
            ->map(fn ($r) => is_array($r) ? ($r['name'] ?? '') : $r)
            ->filter()
            ->values()
            ->all();
        $positionId = session('user.position_id') ?? User::find($userId)?->position_id;

        return $instance->steps()
            ->where(function ($q) use ($userRefs, $roleNames, $positionId) {
                $q->where(function ($uq) use ($userRefs) {
                    $uq->where('approver_type', 'user')->whereIn('approver_ref', $userRefs);
                });
                if (! empty($roleNames)) {
                    $q->orWhere(function ($rq) use ($roleNames) {
                        $rq->where('approver_type', 'role')->whereIn('approver_ref', $roleNames);
                    });
                }
                if ($positionId) {
                    $q->orWhere(function ($pq) use ($positionId) {
                        $pq->where('approver_type', 'position')->where('approver_ref', (string) $positionId);
                    });
                }
            })
            ->exists();
    }

    /**
     * Insert a row into fdata_* table (if the form has a dedicated table).
     * Returns the inserted row ID or null.
     */
    private function writeFdataRow(DocumentForm $form, array $payload, array $meta): ?int
    {
        if (! $form->hasDedicatedTable()) {
            return null;
        }

        $this->schemaService->ensureTableExists($form);

        return $this->schemaService->insertRow($form, $payload, $meta);
    }

    /**
     * Persist uploaded files for `file`, `image`, and `multi_file` fields to
     * storage/app/public/forms/{form_key}/ and replace the payload entry with
     * the stored path(s). For `multi_file`, the value becomes an array of
     * paths; for `file`/`image`, a single path string.
     *
     * When a field has no new upload, the previous value (from $existingPayload)
     * is preserved so edit-draft doesn't clobber existing photos.
     */
    private function processFileUploads(DocumentForm $form, array $payload, Request $request, array $existingPayload = []): array
    {
        foreach ($form->fields as $field) {
            $key = $field->field_key;
            $type = $field->field_type;

            if (! in_array($type, ['file', 'image', 'multi_file'], true)) {
                continue;
            }

            $dir = 'forms/'.$form->form_key;

            if ($type === 'multi_file') {
                $files = $request->file("fields.{$key}");
                if (is_array($files) && count($files) > 0) {
                    $paths = [];
                    foreach ($files as $file) {
                        if ($file && $file->isValid()) {
                            $paths[] = $file->store($dir, 'public');
                        }
                    }
                    // Merge with existing (append new to previously saved array)
                    $existing = is_array($existingPayload[$key] ?? null) ? $existingPayload[$key] : [];
                    $payload[$key] = array_merge($existing, $paths);
                } else {
                    // No new uploads — keep existing array
                    $payload[$key] = $existingPayload[$key] ?? [];
                }

                continue;
            }

            // Single file / image
            $file = $request->file("fields.{$key}");
            if ($file && $file->isValid()) {
                $payload[$key] = $file->store($dir, 'public');
            } elseif (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                $payload[$key] = $existingPayload[$key] ?? null;
            }
        }

        return $payload;
    }

    /**
     * @return array{rules: array, attributes: array}
     */
    public function buildPayloadRules(DocumentForm $form, array $submittedPayload = []): array
    {
        $rules = [];
        $attributes = [];

        foreach ($form->fields as $field) {
            if (in_array($field->field_type, ['section', 'auto_number', 'page_break'])) {
                continue;
            }

            $key = "fields.{$field->field_key}";
            $attributes[$key] = $field->label;

            // Group (subform) — validate as array of objects + per-inner rules
            if ($field->field_type === 'group') {
                $opts = is_array($field->options) ? $field->options : [];
                $minRows = (int) ($opts['min_rows'] ?? 0);
                $maxRows = (int) ($opts['max_rows'] ?? 200);
                $arrRules = ['array', 'max:'.$maxRows];
                if ($field->is_required || $minRows > 0) {
                    $arrRules[] = 'required';
                    $arrRules[] = 'min:'.max(1, $minRows);
                } else {
                    $arrRules[] = 'nullable';
                }
                $rules[$key] = $arrRules;

                foreach ($opts['fields'] ?? [] as $inner) {
                    $innerKey = (string) ($inner['key'] ?? '');
                    if ($innerKey === '') {
                        continue;
                    }
                    $innerRules = (bool) ($inner['required'] ?? false) ? ['required'] : ['nullable'];
                    $innerRules[] = match ($inner['type'] ?? '') {
                        'number', 'currency' => 'numeric',
                        'date' => 'date',
                        'email' => 'email',
                        'multi_select', 'checkbox' => 'array',
                        default => 'string',
                    };
                    $rules["{$key}.*.{$innerKey}"] = $innerRules;
                    $attributes["{$key}.*.{$innerKey}"] = $inner['label'] ?? $innerKey;
                }

                continue;
            }

            // Resolve the field's required state up-front against the submitted
            // payload — conditional required becomes a real `required` rule
            // when its rules evaluate true (and the field is visible). This
            // approach sidesteps Laravel's "skip-empty for non-implicit rules"
            // behavior that prevents closure rules from firing on '' / null.
            $isRequired = (bool) $field->is_required;
            if (! $isRequired && ! empty($field->required_rules)) {
                $isVisible = empty($field->visibility_rules)
                    || self::evaluateRulesPhp($field->visibility_rules, $submittedPayload);
                if ($isVisible && self::evaluateRulesPhp($field->required_rules, $submittedPayload)) {
                    $isRequired = true;
                }
            }
            $fieldRules = $isRequired ? ['required'] : ['nullable'];

            $fieldRules[] = match ($field->field_type) {
                'number', 'currency', 'formula' => 'numeric',
                'date' => 'date',
                'email' => 'email',
                'checkbox', 'multi_select', 'multi_file' => 'array',
                'image' => 'file|image|max:5120',
                'file' => 'file|max:10240',
                default => 'string',
            };

            // multi_file: validate each uploaded file individually under .*
            if ($field->field_type === 'multi_file') {
                $rules[$key.'.*'] = ['file', 'max:10240'];
            }

            // Apply configurable validation_rules from field definition
            $vr = $field->validation_rules;
            if (is_array($vr) && count($vr)) {
                if (! empty($vr['min_length'])) {
                    $fieldRules[] = 'min:'.(int) $vr['min_length'];
                }
                if (! empty($vr['max_length'])) {
                    $fieldRules[] = 'max:'.(int) $vr['max_length'];
                }
                if (! empty($vr['regex'])) {
                    $fieldRules[] = 'regex:/'.str_replace('/', '\/', $vr['regex']).'/';
                }
                if (isset($vr['min']) && $vr['min'] !== '' && in_array($field->field_type, ['number', 'currency'])) {
                    $fieldRules[] = 'min:'.$vr['min'];
                }
                if (isset($vr['max']) && $vr['max'] !== '' && in_array($field->field_type, ['number', 'currency'])) {
                    $fieldRules[] = 'max:'.$vr['max'];
                }
                if (! empty($vr['min_date']) && $field->field_type === 'date') {
                    $resolved = DateExpressionResolver::resolve($vr['min_date']);
                    if ($resolved) {
                        $fieldRules[] = 'after_or_equal:'.$resolved;
                    }
                }
                if (! empty($vr['max_date']) && $field->field_type === 'date') {
                    $resolved = DateExpressionResolver::resolve($vr['max_date']);
                    if ($resolved) {
                        $fieldRules[] = 'before_or_equal:'.$resolved;
                    }
                }
            }

            $rules[$key] = $fieldRules;
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    /**
     * Evaluate visibility_rules / required_rules JSON against a payload.
     * Mirrors the JS evaluator in `resources/js/app.js::evaluateVisibilityRules`
     * — same 8 operators, same AND-of-rules semantics. Stays in sync with the
     * client to avoid divergent "client says required, server disagrees" bugs.
     *
     * @param  array<int, array{field?:string, operator?:string, value?:mixed}>  $rules
     * @param  array<string, mixed>  $payload  Field key → value pairs
     */
    private static function evaluateRulesPhp(array $rules, array $payload): bool
    {
        if (empty($rules)) {
            return false;
        }
        foreach ($rules as $rule) {
            if (! is_array($rule) || empty($rule['field'])) {
                continue;
            }
            $fieldKey = (string) $rule['field'];
            $op = (string) ($rule['operator'] ?? 'equals');
            $expected = $rule['value'] ?? null;
            $actual = $payload[$fieldKey] ?? null;

            $isArray = is_array($actual);
            $contains = fn ($haystack, $needle) => $isArray
                ? in_array($needle, $haystack, false)
                : ((string) $haystack === (string) $needle);

            $match = match ($op) {
                'equals' => $contains($actual, $expected),
                'not_equals' => ! $contains($actual, $expected),
                'is_empty' => $actual === null || $actual === '' || $actual === [],
                'is_not_empty' => ! ($actual === null || $actual === '' || $actual === []),
                'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
                'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
                'in' => is_array($expected) && in_array((string) $actual, array_map('strval', $expected), true),
                'not_in' => is_array($expected) && ! in_array((string) $actual, array_map('strval', $expected), true),
                default => false,
            };
            if (! $match) {
                return false;
            }
        }

        return true;
    }

    private function recomputeFormulaFields(DocumentForm $form, array $payload): array
    {
        return \App\Support\FormulaFields::recompute($form, $payload);
    }
}
