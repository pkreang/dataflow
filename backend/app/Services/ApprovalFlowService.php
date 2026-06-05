<?php

namespace App\Services;

use App\Events\Approval\WorkflowCompleted;
use App\Events\Approval\WorkflowPartialApproval;
use App\Events\Approval\WorkflowReturned;
use App\Events\Approval\WorkflowStarted;
use App\Events\Approval\WorkflowStepAdvanced;
use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DepartmentWorkflowBinding;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalFlowService
{
    /** @see Setting key approval_workflow_routing_mode */
    public const ROUTING_HYBRID = 'hybrid';

    /** อนุมัติตามแผนกเท่านั้น — ไม่ fallback ไป policy/binding ทั้งองค์กร */
    public const ROUTING_DEPARTMENT_SCOPED = 'department_scoped';

    /** เลือก workflow แบบองค์กรเดียว — ไม่แยกแผนก (ขั้นอนุมัติยังใช้ role/user ใน workflow ได้ตามปกติ) */
    public const ROUTING_ORGANIZATION_WIDE = 'organization_wide';

    public function start(
        string $documentType,
        ?int $departmentId,
        int $requesterUserId,
        ?string $referenceNo = null,
        array $payload = [],
        ?string $formKey = null,
        ?float $amount = null,
        array $pickedApprovers = []
    ): ApprovalInstance {
        if ($departmentId === null) {
            $departmentId = User::find($requesterUserId)?->department_id;
        }

        $routingMode = $this->routingMode($documentType);
        $resolvedWorkflowId = $this->resolveWorkflowId($documentType, $departmentId, $formKey, $amount, $routingMode);

        $binding = null;
        if ($resolvedWorkflowId === null) {
            $binding = $this->resolveDepartmentBinding($documentType, $departmentId, $routingMode);

            if (! $binding) {
                throw new RuntimeException($this->bindingMissingMessage($documentType, $departmentId, $routingMode));
            }
            $resolvedWorkflowId = (int) $binding->workflow_id;
        }

        $workflow = ApprovalWorkflow::query()
            ->with(['stages' => fn ($q) => $q->where('is_active', true)->orderBy('step_no')])
            ->whereKey($resolvedWorkflowId)
            ->where('is_active', true)
            ->first();

        if (! $workflow || $workflow->stages->isEmpty()) {
            throw new RuntimeException("Workflow is not configured for {$documentType}");
        }

        $instanceDepartmentId = $departmentId ?? $binding?->department_id;

        return DB::transaction(function () use ($workflow, $instanceDepartmentId, $requesterUserId, $documentType, $referenceNo, $payload, $pickedApprovers) {
            // Auto-generate running number if not provided
            if (empty($referenceNo)) {
                $referenceNo = app(RunningNumberService::class)->generate($documentType);
            }

            $instance = ApprovalInstance::create([
                'workflow_id' => $workflow->id,
                'department_id' => $instanceDepartmentId,
                'requester_user_id' => $requesterUserId,
                'document_type' => $documentType,
                'reference_no' => $referenceNo,
                'payload' => $payload,
                'current_step_no' => 1,
                'status' => 'pending',
            ]);

            foreach ($workflow->stages as $stage) {
                $stepType = $stage->approver_type;
                $stepRef = $stage->approver_ref;

                // requester_pick: the requester chose the approver at submit time.
                // Resolve it to a concrete `user` step so the rest of the engine
                // (canUserActOnStep) treats it exactly like a fixed-user stage.
                // Validate server-side that the picked user actually holds
                // approval.approve — never trust the submitted id.
                if ($stage->approver_type === 'requester_pick') {
                    $pickedId = (int) ($pickedApprovers[$stage->step_no] ?? 0);
                    $picked = $pickedId ? User::find($pickedId) : null;
                    if (! $picked || ! $picked->getAllPermissions()->pluck('name')->contains('approval.approve')) {
                        throw new RuntimeException('requester_pick_invalid_approver');
                    }
                    $stepType = 'user';
                    $stepRef = (string) $pickedId;
                }

                ApprovalInstanceStep::create([
                    'approval_instance_id' => $instance->id,
                    'step_no' => $stage->step_no,
                    'stage_name' => $stage->name,
                    'approver_type' => $stepType,
                    'approver_ref' => $stepRef,
                    'min_approvals' => $stage->min_approvals ?? 1,
                    'require_signature' => (bool) ($stage->require_signature ?? false),
                    'approved_by' => [],
                    'action' => 'pending',
                ]);
            }

            event(new WorkflowStarted($instance));

            return $instance->load(['steps', 'workflow']);
        });
    }

    /**
     * Resolve which workflow WOULD apply to a submission, with its active stages,
     * WITHOUT creating an instance. Used by the draft page to decide whether to
     * show a "pick approver" UI for any `requester_pick` stages. Mirrors the
     * resolution in start() but returns null (instead of throwing) when no
     * workflow/binding is found — the caller just shows no picker.
     */
    public function previewWorkflow(
        string $documentType,
        ?int $departmentId,
        int $requesterUserId,
        ?string $formKey = null,
        ?float $amount = null
    ): ?ApprovalWorkflow {
        if ($departmentId === null) {
            $departmentId = User::find($requesterUserId)?->department_id;
        }

        $routingMode = $this->routingMode($documentType);
        $resolvedWorkflowId = $this->resolveWorkflowId($documentType, $departmentId, $formKey, $amount, $routingMode);

        if ($resolvedWorkflowId === null) {
            $binding = $this->resolveDepartmentBinding($documentType, $departmentId, $routingMode);
            if (! $binding) {
                return null;
            }
            $resolvedWorkflowId = (int) $binding->workflow_id;
        }

        // Caller queries stages separately (ApprovalWorkflowStage) — no eager-load
        // needed here, which also avoids a larastan untyped-relation false positive.
        return ApprovalWorkflow::query()
            ->whereKey($resolvedWorkflowId)
            ->where('is_active', true)
            ->first();
    }

    public function routingMode(string $documentType = ''): string
    {
        $mode = '';

        if ($documentType !== '') {
            $mode = (string) DocumentType::query()
                ->where('code', $documentType)
                ->value('routing_mode');
        }

        if ($mode === '') {
            $mode = self::ROUTING_HYBRID;
        }

        return in_array($mode, [
            self::ROUTING_HYBRID,
            self::ROUTING_DEPARTMENT_SCOPED,
            self::ROUTING_ORGANIZATION_WIDE,
        ], true) ? $mode : self::ROUTING_HYBRID;
    }

    private function resolveWorkflowId(
        string $documentType,
        ?int $departmentId,
        ?string $formKey,
        ?float $amount,
        string $routingMode
    ): ?int {
        if (! $formKey) {
            return null;
        }

        $form = DocumentForm::query()
            ->where('form_key', $formKey)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->first();
        if (! $form) {
            return null;
        }

        $policyQuery = DocumentFormWorkflowPolicy::query()
            ->with('ranges')
            ->where('form_id', $form->id);

        match ($routingMode) {
            self::ROUTING_ORGANIZATION_WIDE => $policyQuery->whereNull('department_id'),
            self::ROUTING_DEPARTMENT_SCOPED => $departmentId
                ? $policyQuery->where('department_id', $departmentId)
                : $policyQuery->whereNull('department_id'),
            default => $policyQuery->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id');
                if ($departmentId) {
                    $query->orWhere('department_id', $departmentId);
                }
            })->orderByRaw('department_id IS NULL ASC'),
        };

        $policy = $policyQuery->first();

        if (! $policy) {
            return null;
        }

        if (! $policy->use_amount_condition) {
            return $policy->workflow_id ? (int) $policy->workflow_id : null;
        }

        if ($amount === null) {
            throw new RuntimeException("Amount is required for amount-based workflow policy: {$formKey}");
        }

        foreach ($policy->ranges as $range) {
            $min = (float) $range->min_amount;
            $max = $range->max_amount !== null ? (float) $range->max_amount : null;
            if ($amount >= $min && ($max === null || $amount <= $max)) {
                return (int) $range->workflow_id;
            }
        }

        throw new RuntimeException("No matching amount range for form {$formKey}");
    }

    private function resolveDepartmentBinding(string $documentType, ?int $departmentId, string $routingMode): ?DepartmentWorkflowBinding
    {
        $q = DepartmentWorkflowBinding::query()->where('document_type', $documentType);

        return match ($routingMode) {
            self::ROUTING_ORGANIZATION_WIDE => $q->orderBy('id')->first(),
            self::ROUTING_DEPARTMENT_SCOPED => $departmentId
                ? $q->where('department_id', $departmentId)->first()
                : null,
            default => $departmentId
                ? $q->where('department_id', $departmentId)->first()
                : $q->orderBy('id')->first(),
        };
    }

    private function bindingMissingMessage(string $documentType, ?int $departmentId, string $routingMode): string
    {
        if ($routingMode === self::ROUTING_DEPARTMENT_SCOPED && ! $departmentId) {
            return "Department is required for workflow binding (routing mode: department_scoped) for {$documentType}";
        }

        return "No workflow binding found for {$documentType}";
    }

    /**
     * @param  string|null  $signatureImage  Data URL (data:image/...) or public
     *                                       URL of the approver's signature.
     *                                       Required when the step has
     *                                       `require_signature=true`. Stored
     *                                       per-approver in `approved_by` for
     *                                       approve actions; in the step's
     *                                       `signature_image` column for
     *                                       reject actions.
     */
    public function act(int $instanceId, int $actorUserId, string $action, ?string $comment = null, ?string $signatureImage = null): ApprovalInstance
    {
        if (! in_array($action, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid approval action');
        }

        return DB::transaction(function () use ($instanceId, $actorUserId, $action, $comment, $signatureImage) {
            $instance = ApprovalInstance::query()->with(['steps', 'workflow'])->lockForUpdate()->findOrFail($instanceId);

            if ($instance->status !== 'pending') {
                throw new RuntimeException('Approval instance is already closed');
            }

            $step = $instance->steps->firstWhere('step_no', $instance->current_step_no);

            if (! $step) {
                throw new RuntimeException('Approval step not found');
            }

            if (! $this->canUserActOnStep($instance, $step, $actorUserId)) {
                throw new RuntimeException('You are not allowed to approve this step');
            }

            // Per-stage signature requirement — block both approve and reject
            // when the stage requires a signature and the actor didn't supply one.
            if ($step->require_signature && empty($signatureImage)) {
                throw new RuntimeException('signature_required');
            }

            if ($action === 'rejected') {
                $step->update([
                    'acted_by_user_id' => $actorUserId,
                    'action' => 'rejected',
                    'comment' => $comment,
                    'signature_image' => $signatureImage,
                    'acted_at' => now(),
                ]);
                $instance->update(['status' => 'rejected']);

                event(new WorkflowCompleted($instance, 'rejected', $comment));

                return $instance->fresh(['steps', 'workflow']);
            }

            // Approved — track in approved_by array
            $actor = User::find($actorUserId);
            $approvedBy = $step->approved_by ?? [];
            $approvedBy[] = [
                'user_id' => $actorUserId,
                'name' => $actor?->full_name ?? (string) $actorUserId,
                'comment' => $comment,
                'signature' => $signatureImage,
                'at' => now()->toIso8601String(),
            ];
            $step->approved_by = $approvedBy;

            $minApprovals = $step->min_approvals ?? 1;

            if (count($approvedBy) >= $minApprovals) {
                // Enough approvals — mark step complete and advance
                $step->update([
                    'approved_by' => $approvedBy,
                    'acted_by_user_id' => $actorUserId,
                    'action' => 'approved',
                    'comment' => $comment,
                    'acted_at' => now(),
                ]);

                $nextStep = $instance->steps->firstWhere('step_no', $instance->current_step_no + 1);
                if ($nextStep) {
                    $instance->update(['current_step_no' => $nextStep->step_no]);
                    event(new WorkflowStepAdvanced($instance, $nextStep));
                } else {
                    $instance->update(['status' => 'approved']);
                    event(new WorkflowCompleted($instance, 'approved'));
                }
            } else {
                // Not enough yet — save progress but keep step pending
                $step->update([
                    'approved_by' => $approvedBy,
                ]);
                event(new WorkflowPartialApproval($instance, $step));
            }

            return $instance->fresh(['steps', 'workflow']);
        });
    }

    /**
     * Send a pending request back instead of approving/rejecting it. Unlike a
     * reject (which is final), a send-back is non-terminal — the request is
     * meant to be fixed and re-considered.
     *
     * Destinations:
     *  - `previous_step`: rewind to step N-1. Steps N-1 and N are reset to
     *    `pending` (their approvals/signatures cleared); the instance stays
     *    `pending`. Not allowed on step 1.
     *  - `requester`: close the instance with status `returned`. The caller is
     *    responsible for flipping the linked document back to an editable state.
     *
     * A signature is never required for a send-back (it is not a formal
     * decision on the merits); a non-empty comment always is.
     */
    public function sendBack(int $instanceId, int $actorUserId, string $destination, string $comment): ApprovalInstance
    {
        if (! in_array($destination, ['requester', 'previous_step'], true)) {
            throw new RuntimeException('Invalid send-back destination');
        }
        if (trim($comment) === '') {
            throw new RuntimeException('send_back_comment_required');
        }

        return DB::transaction(function () use ($instanceId, $actorUserId, $destination, $comment) {
            $instance = ApprovalInstance::query()->with(['steps', 'workflow'])->lockForUpdate()->findOrFail($instanceId);

            if ($instance->status !== 'pending') {
                throw new RuntimeException('Approval instance is already closed');
            }

            $currentStepNo = (int) $instance->current_step_no;
            $step = $instance->steps->firstWhere('step_no', $currentStepNo);

            if (! $step) {
                throw new RuntimeException('Approval step not found');
            }

            if (! $this->canUserActOnStep($instance, $step, $actorUserId)) {
                throw new RuntimeException('You are not allowed to act on this step');
            }

            if ($destination === 'previous_step') {
                if ($currentStepNo <= 1) {
                    throw new RuntimeException('send_back_no_previous_step');
                }

                $targetStepNo = $currentStepNo - 1;

                // Reset the target step and the current step back to a clean
                // pending state — both must be re-approved from scratch.
                foreach ($instance->steps as $instanceStep) {
                    if (in_array($instanceStep->step_no, [$targetStepNo, $currentStepNo], true)) {
                        $instanceStep->update([
                            'action' => 'pending',
                            'approved_by' => [],
                            'acted_by_user_id' => null,
                            'comment' => null,
                            'acted_at' => null,
                            'signature_image' => null,
                        ]);
                    }
                }

                $instance->update(['current_step_no' => $targetStepNo]);
            } else {
                $instance->update(['status' => 'returned']);
            }

            event(new WorkflowReturned($instance, $destination, $actorUserId, $comment));

            return $instance->fresh(['steps', 'workflow']);
        });
    }

    /**
     * Whether the user may approve/reject the current pending step (UI + act()).
     */
    public function canUserActOnStep(ApprovalInstance $instance, ApprovalInstanceStep $step, int $actorUserId): bool
    {
        if ($step->action !== 'pending') {
            return false;
        }

        $instance->loadMissing('workflow');
        $workflow = $instance->workflow;
        if ($workflow && ! $workflow->allow_requester_as_approver
            && (int) $instance->requester_user_id === $actorUserId) {
            return false;
        }

        // Already approved by this user
        $approvedBy = $step->approved_by ?? [];
        if (collect($approvedBy)->contains('user_id', $actorUserId)) {
            return false;
        }

        if ($step->approver_type === 'user') {
            return (int) $step->approver_ref === $actorUserId;
        }

        $user = User::find($actorUserId);
        if (! $user) {
            return false;
        }

        if ($step->approver_type === 'position') {
            return $user->position_id
                && (string) $step->approver_ref === (string) $user->position_id;
        }

        return $user->hasRole($step->approver_ref);
    }
}
