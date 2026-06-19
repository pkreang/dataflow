<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowRequesterGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_act_when_workflow_disallows(): void
    {
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'gate-test-a@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'Gate test',
            'document_type' => 'repair_request',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Self',
            'approver_type' => 'user',
            'approver_ref' => (string) $user->id,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $user->id,
            'document_type' => 'repair_request',
            'reference_no' => 'T-1',
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);

        $step = ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Self',
            'approver_type' => 'user',
            'approver_ref' => (string) $user->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        $instance->load('workflow');

        $service = app(ApprovalFlowService::class);
        $this->assertFalse($service->canUserActOnStep($instance, $step, $user->id));
    }

    public function test_requester_can_act_when_workflow_allows(): void
    {
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'gate-test-b@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'Gate test allow',
            'document_type' => 'repair_request',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => true,
        ]);

        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Self',
            'approver_type' => 'user',
            'approver_ref' => (string) $user->id,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $user->id,
            'document_type' => 'repair_request',
            'reference_no' => 'T-2',
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);

        $step = ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Self',
            'approver_type' => 'user',
            'approver_ref' => (string) $user->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        $instance->load('workflow');

        $service = app(ApprovalFlowService::class);
        $this->assertTrue($service->canUserActOnStep($instance, $step, $user->id));
    }
}
