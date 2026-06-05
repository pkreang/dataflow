<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
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
        $p = new Permission();
        $p->name = 'approval.approve';
        $p->guard_name = 'web';
        $p->module = 'approval'; // app's permissions table has NOT NULL module + action columns
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

    private function makeRequesterPickWorkflow(): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'Requester pick wf',
            'document_type' => 'repair_request',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Chosen approver',
            'approver_type' => 'requester_pick',
            'approver_ref' => '',
            'min_approvals' => 1,
            'is_active' => true,
        ]);
        $dept = Department::query()->create(['name' => 'Test Dept', 'code' => 'TST', 'is_active' => true]);
        DepartmentWorkflowBinding::query()->create([
            'department_id' => $dept->id,
            'document_type' => 'repair_request',
            'workflow_id' => $workflow->id,
        ]);

        return $workflow;
    }

    public function test_requester_pick_resolves_chosen_user_as_step_approver(): void
    {
        $this->ensureApprovalPermission();
        $approver = $this->makeUser('approver@example.test');
        $approver->givePermissionTo('approval.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $requester = $this->makeUser('requester@example.test');
        $this->makeRequesterPickWorkflow();

        $service = app(ApprovalFlowService::class);
        $instance = $service->start(
            documentType: 'repair_request',
            departmentId: null,
            requesterUserId: $requester->id,
            referenceNo: 'RP-1',
            payload: [],
            formKey: null,
            pickedApprovers: [1 => $approver->id],
        );

        $step = $instance->steps->firstWhere('step_no', 1);

        // requester_pick is resolved to a concrete `user` step using the chosen id
        $this->assertSame('user', $step->approver_type);
        $this->assertSame((string) $approver->id, $step->approver_ref);

        // the chosen approver can act; an unrelated user cannot
        $this->assertTrue($service->canUserActOnStep($instance, $step, $approver->id));
        $this->assertFalse($service->canUserActOnStep($instance, $step, $requester->id));
    }

    public function test_requester_pick_rejects_user_without_approval_permission(): void
    {
        $this->ensureApprovalPermission();
        $stranger = $this->makeUser('stranger@example.test'); // no approval.approve
        $requester = $this->makeUser('requester2@example.test');
        $this->makeRequesterPickWorkflow();

        $service = app(ApprovalFlowService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requester_pick_invalid_approver');

        $service->start(
            documentType: 'repair_request',
            departmentId: null,
            requesterUserId: $requester->id,
            referenceNo: 'RP-2',
            payload: [],
            formKey: null,
            pickedApprovers: [1 => $stranger->id],
        );
    }
}
