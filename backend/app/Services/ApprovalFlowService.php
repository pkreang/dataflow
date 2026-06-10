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
use App\Models\OrgUnit;
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
        array $pickedApprovers = [],
        int $positionId = 0
    ): ApprovalInstance {
        if ($departmentId === null) {
            $departmentId = User::find($requesterUserId)?->department_id;
        }

        $routingMode = $this->routingMode($documentType);
        $resolvedWorkflowId = $this->resolveWorkflowId($documentType, $departmentId, $formKey, $amount, $routingMode, $positionId, $payload);

        $binding = null;
        if ($resolvedWorkflowId === null) {
            $binding = $this->resolveDepartmentBinding($documentType, $departmentId);

            if (! $binding) {
                throw new RuntimeException($this->bindingMissingMessage($documentType, $departmentId));
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

            $requesterUser = null;
            foreach ($workflow->stages as $stage) {
                $stepType = $stage->approver_type;
                $stepRef = $stage->approver_ref;

                // Eager-resolve direct_manager: rewrite step to ('user', manager_id) so all
                // downstream logic (inbox scope, canUserActOnStep, signatures) works unchanged.
                if ($stage->approver_type === 'direct_manager') {
                    $requesterUser ??= User::find($requesterUserId);
                    if (! $requesterUser?->manager_id) {
                        throw new \RuntimeException(__('common.workflow_no_manager_assigned'));
                    }
                    $stepType = 'user';
                    $stepRef = (string) $requesterUser->manager_id;
                }

                // Eager-resolve org_head: head of user's org unit.
                if ($stage->approver_type === 'org_head') {
                    $requesterUser ??= User::find($requesterUserId);
                    $orgUnit = OrgUnit::find($requesterUser?->org_unit_id);
                    if (! $orgUnit?->head_user_id) {
                        throw new RuntimeException(__('common.workflow_no_org_head'));
                    }
                    $stepType = 'user';
                    $stepRef = (string) $orgUnit->head_user_id;
                }

                // Eager-resolve org_parent_head: head of user's org unit's parent (1 level up).
                if ($stage->approver_type === 'org_parent_head') {
                    $requesterUser ??= User::find($requesterUserId);
                    $parent = OrgUnit::find($requesterUser?->org_unit_id)?->parent;
                    if (! $parent?->head_user_id) {
                        throw new RuntimeException(__('common.workflow_no_org_parent'));
                    }
                    $stepType = 'user';
                    $stepRef = (string) $parent->head_user_id;
                }

                // Eager-resolve org_n_up: head of ancestor N levels above user's org unit.
                if ($stage->approver_type === 'org_n_up') {
                    $n = max(1, (int) $stage->approver_ref);
                    $requesterUser ??= User::find($requesterUserId);
                    $unit = OrgUnit::find($requesterUser?->org_unit_id)?->nthAncestor($n);
                    if (! $unit?->head_user_id) {
                        throw new RuntimeException(__('common.workflow_no_org_unit_at_level'));
                    }
                    $stepType = 'user';
                    $stepRef = (string) $unit->head_user_id;
                }

                // override: requester optionally substitutes a specific approver.
                // If no pick is submitted the stage falls back to its default
                // routing (position/user/role) unchanged.
                if ($stage->allow_requester_override) {
                    $pickedId = (int) ($pickedApprovers[$stage->step_no] ?? 0);
                    if ($pickedId) {
                        $picked = User::find($pickedId);
                        if (! $picked || ! $picked->getAllPermissions()->pluck('name')->contains('approval.approve')) {
                            throw new RuntimeException('requester_pick_invalid_approver');
                        }
                        $stepType = 'user';
                        $stepRef = (string) $pickedId;
                    }
                }

                $createdStep = ApprovalInstanceStep::create([
                    'approval_instance_id' => $instance->id,
                    'step_no' => $stage->step_no,
                    'stage_name' => $stage->name,
                    'approver_type' => $stepType,
                    'approver_ref' => $stepRef,
                    'approver_rules' => $stage->allow_requester_override ? null : $stage->approver_rules,
                    'min_approvals' => $stage->min_approvals ?? 1,
                    'require_signature' => (bool) ($stage->require_signature ?? false),
                    'escalation_after_days' => $stage->escalation_after_days ?? null,
                    'approved_by' => [],
                    'action' => 'pending',
                ]);

                // Notify substitute if primary approver has an active substitution
                if ($stepType === 'user' && $stepRef) {
                    $substituteId = \App\Models\UserSubstitution::findActiveSubstitute((int) $stepRef, now());
                    if ($substituteId && $substituteId !== (int) $stepRef) {
                        $substitute = \App\Models\User::find($substituteId);
                        $substitute?->notify(new \App\Notifications\ApprovalPendingNotification($instance, $createdStep));
                    }
                }
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
            $binding = $this->resolveDepartmentBinding($documentType, $departmentId);
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
        return self::ROUTING_HYBRID;
    }

    private function resolveWorkflowId(
        string $documentType,
        ?int $departmentId,
        ?string $formKey,
        ?float $amount,
        string $routingMode,
        int $positionId = 0,
        array $payload = []
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

        // Priority: position-specific > department-specific > global
        // Collect all policies that could match, then pick most specific.
        $policyQuery->where(function ($query) use ($departmentId, $positionId) {
            // Global fallback always included
            $query->where(function ($q) {
                $q->whereNull('department_id')->whereNull('position_id');
            });
            if ($departmentId) {
                $query->orWhere(function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId)->whereNull('position_id');
                });
            }
            if ($positionId) {
                $query->orWhere('position_id', $positionId);
            }
        })
        ->orderByRaw('(position_id IS NOT NULL) DESC')
        ->orderByRaw('(department_id IS NOT NULL) DESC');

        $policy = $policyQuery->first();

        if (! $policy) {
            return null;
        }

        // Field conditions — evaluated first (more specific than amount ranges)
        if (! empty($policy->field_conditions)) {
            $sorted = collect($policy->field_conditions)->sortBy('priority');
            foreach ($sorted as $cond) {
                $fieldVal = $payload[$cond['field_key'] ?? ''] ?? null;
                if ($fieldVal !== null && $this->evalFieldCondition($fieldVal, $cond['operator'] ?? '=', $cond['value'] ?? null)) {
                    return isset($cond['workflow_id']) ? (int) $cond['workflow_id'] : null;
                }
            }
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

    private function evalFieldCondition(mixed $fieldVal, string $operator, mixed $expected): bool
    {
        $strVal = (string) $fieldVal;
        $numVal = is_numeric($fieldVal) ? (float) $fieldVal : null;

        return match ($operator) {
            '='         => $strVal === (string) $expected,
            '!='        => $strVal !== (string) $expected,
            '>'         => $numVal !== null && $numVal > (float) $expected,
            '>='        => $numVal !== null && $numVal >= (float) $expected,
            '<'         => $numVal !== null && $numVal < (float) $expected,
            '<='        => $numVal !== null && $numVal <= (float) $expected,
            'in'        => is_array($expected) && in_array($strVal, array_map('strval', $expected), true),
            'not_in'    => is_array($expected) && ! in_array($strVal, array_map('strval', $expected), true),
            'contains'  => str_contains($strVal, (string) $expected),
            default     => false,
        };
    }

    private function resolveDepartmentBinding(string $documentType, ?int $departmentId): ?DepartmentWorkflowBinding
    {
        $q = DepartmentWorkflowBinding::query()->where('document_type', $documentType);

        return $departmentId
            ? $q->where('department_id', $departmentId)->first()
            : $q->orderBy('id')->first();
    }

    private function bindingMissingMessage(string $documentType, ?int $departmentId): string
    {
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
            $isComplete = ! empty($step->approver_rules)
                ? $this->countSatisfiedSources($step, $approvedBy) >= $minApprovals
                : count($approvedBy) >= $minApprovals;

            if ($isComplete) {
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

        $rules = $step->approver_rules;
        if (! empty($rules)) {
            $user = User::find($actorUserId);
            if (! $user) {
                return false;
            }
            // check primary rule first (bug fix: was previously skipped)
            if ($this->userMatchesApproverRule($step->approver_type ?? '', $step->approver_ref ?? '', $actorUserId, $user)) {
                return true;
            }
            foreach ($rules as $rule) {
                if ($this->userMatchesApproverRule($rule['type'] ?? '', $rule['ref'] ?? '', $actorUserId, $user)) {
                    return true;
                }
            }
            return false;
        }

        if ($step->approver_type === 'user') {
            if ((int) $step->approver_ref === $actorUserId) {
                return true;
            }
            // Allow active substitute to act on behalf of the primary approver
            return \App\Models\UserSubstitution::activeSubstituteFor(
                (int) $step->approver_ref,
                $actorUserId,
                now()
            );
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

    private function countSatisfiedSources(ApprovalInstanceStep $step, array $approvedBy): int
    {
        $allRules = array_merge(
            [['type' => $step->approver_type, 'ref' => $step->approver_ref, 'min_count' => 1]],
            collect($step->approver_rules ?? [])
                ->map(fn ($r) => array_merge(['min_count' => 1], (array) $r))
                ->all()
        );

        $satisfied = 0;
        foreach ($allRules as $rule) {
            $need = max(1, (int) ($rule['min_count'] ?? 1));
            $matched = 0;
            foreach ($approvedBy as $ab) {
                $u = User::find($ab['user_id']);
                if ($u && $this->userMatchesApproverRule($rule['type'] ?? '', $rule['ref'] ?? '', $ab['user_id'], $u)) {
                    $matched++;
                }
            }
            if ($matched >= $need) {
                $satisfied++;
            }
        }
        return $satisfied;
    }

    private function userMatchesApproverRule(string $type, string $ref, int $actorUserId, User $user): bool
    {
        return match ($type) {
            'user'     => (int) $ref === $actorUserId,
            'position' => $user->position_id && (string) $user->position_id === $ref,
            'role'     => $user->hasRole($ref),
            default    => false,
        };
    }
}
