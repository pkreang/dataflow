<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\OrgUnit;
use App\Models\OrgUnitWorkflowBinding;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RequesterPickApproverTest extends TestCase
{
    use RefreshDatabase;

    private function ensureApprovalPermission(): void
    {
        if (Permission::query()->where('name', 'approval.approve')->where('guard_name', 'web')->exists()) {
            return;
        }
        $p = new Permission;
        $p->name = 'approval.approve';
        $p->guard_name = 'web';
        $p->module = 'approval';
        $p->action = 'approve';
        $p->save();
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'first_name' => 'U',
            'last_name' => $email,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    /** Build a workflow with one user-type stage that has allow_requester_override=true. */
    private function makeOverrideWorkflow(int $defaultApproverId, User $requester): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'Override wf',
            'document_type' => 'repair_request',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Override stage',
            'approver_type' => 'user',
            'approver_ref' => (string) $defaultApproverId,
            'min_approvals' => 1,
            'is_active' => true,
            'allow_requester_override' => true,
        ]);
        $org = OrgUnit::query()->create(['name' => 'Test Org '.uniqid(), 'type' => 'department', 'is_active' => true]);
        $requester->update(['org_unit_id' => $org->id]);
        OrgUnitWorkflowBinding::query()->create([
            'org_unit_id' => $org->id,
            'document_type' => 'repair_request',
            'workflow_id' => $workflow->id,
        ]);

        return $workflow;
    }

    public function test_override_resolves_chosen_user_as_step_approver(): void
    {
        $this->ensureApprovalPermission();
        $defaultApprover = $this->makeUser('default@example.test');
        $defaultApprover->givePermissionTo('approval.approve');

        $pickedApprover = $this->makeUser('picked@example.test');
        $pickedApprover->givePermissionTo('approval.approve');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $requester = $this->makeUser('requester@example.test');
        $this->makeOverrideWorkflow($defaultApprover->id, $requester);

        $service = app(ApprovalFlowService::class);
        $instance = $service->start(
            documentType: 'repair_request',
            requesterUserId: $requester->id,
            referenceNo: 'RP-1',
            payload: [],
            formKey: null,
            pickedApprovers: [1 => $pickedApprover->id],
        );

        $step = $instance->steps->firstWhere('step_no', 1);

        // override replaces default approver with picked approver (still user-type)
        $this->assertSame('user', $step->approver_type);
        $this->assertSame((string) $pickedApprover->id, $step->approver_ref);

        $this->assertTrue($service->canUserActOnStep($instance, $step, $pickedApprover->id));
        $this->assertFalse($service->canUserActOnStep($instance, $step, $requester->id));
    }

    public function test_override_rejects_user_without_approval_permission(): void
    {
        $this->ensureApprovalPermission();
        $defaultApprover = $this->makeUser('default2@example.test');
        $defaultApprover->givePermissionTo('approval.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $stranger = $this->makeUser('stranger@example.test'); // no approval.approve
        $requester = $this->makeUser('requester2@example.test');
        $this->makeOverrideWorkflow($defaultApprover->id, $requester);

        $service = app(ApprovalFlowService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requester_pick_invalid_approver');

        $service->start(
            documentType: 'repair_request',
            requesterUserId: $requester->id,
            referenceNo: 'RP-2',
            payload: [],
            formKey: null,
            pickedApprovers: [1 => $stranger->id],
        );
    }

    public function test_no_override_falls_back_to_default_routing(): void
    {
        $this->ensureApprovalPermission();
        $defaultApprover = $this->makeUser('default3@example.test');
        $defaultApprover->givePermissionTo('approval.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $requester = $this->makeUser('requester3@example.test');
        $this->makeOverrideWorkflow($defaultApprover->id, $requester);

        $service = app(ApprovalFlowService::class);
        $instance = $service->start(
            documentType: 'repair_request',
            requesterUserId: $requester->id,
            referenceNo: 'RP-3',
            payload: [],
            formKey: null,
            pickedApprovers: [], // no override provided
        );

        $step = $instance->steps->firstWhere('step_no', 1);

        // no override → default routing preserved (user-type with original approver_ref)
        $this->assertSame('user', $step->approver_type);
        $this->assertSame((string) $defaultApprover->id, $step->approver_ref);

        $this->assertTrue($service->canUserActOnStep($instance, $step, $defaultApprover->id));
        $this->assertFalse($service->canUserActOnStep($instance, $step, $requester->id));
    }
}
