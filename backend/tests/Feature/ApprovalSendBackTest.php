<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Coverage for the approver "send back" feature — ApprovalFlowService::sendBack()
 * and the DocumentFormSubmissionController::sendBack() endpoint.
 *
 * Two destinations:
 *  - previous_step : rewind current_step_no to N-1, reset steps N-1 and N
 *  - requester     : close the instance (status=returned); caller flips the
 *                    submission back to draft so the owner can edit + resubmit
 */
class ApprovalSendBackTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    // ---------- service-level ----------

    public function test_send_back_to_previous_step_rewinds_and_resets_steps(): void
    {
        [$instance, , $step2Approver] = $this->makeTwoStepInstanceAtStep2();

        $fresh = app(ApprovalFlowService::class)
            ->sendBack($instance->id, $step2Approver->id, 'previous_step', 'แก้ช่องวันที่');

        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->current_step_no);

        $step1 = $fresh->steps->firstWhere('step_no', 1);
        $step2 = $fresh->steps->firstWhere('step_no', 2);
        $this->assertSame('pending', $step1->action);
        $this->assertSame([], $step1->approved_by);
        $this->assertNull($step1->acted_by_user_id);
        $this->assertSame('pending', $step2->action);
    }

    public function test_send_back_to_requester_marks_instance_returned(): void
    {
        [$instance, , $step2Approver] = $this->makeTwoStepInstanceAtStep2();

        $fresh = app(ApprovalFlowService::class)
            ->sendBack($instance->id, $step2Approver->id, 'requester', 'กรุณาแนบเอกสารเพิ่ม');

        $this->assertSame('returned', $fresh->status);
    }

    public function test_send_back_to_previous_step_fails_at_first_step(): void
    {
        [$instance, $approver] = $this->makeSingleStepInstance();

        $this->expectException(RuntimeException::class);
        app(ApprovalFlowService::class)
            ->sendBack($instance->id, $approver->id, 'previous_step', 'มีเหตุผล');
    }

    public function test_send_back_requires_a_comment(): void
    {
        [$instance, , $step2Approver] = $this->makeTwoStepInstanceAtStep2();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('send_back_comment_required');
        app(ApprovalFlowService::class)
            ->sendBack($instance->id, $step2Approver->id, 'requester', '   ');
    }

    public function test_send_back_blocked_for_non_approver_of_current_step(): void
    {
        [$instance, $step1Approver] = $this->makeTwoStepInstanceAtStep2();
        // step-1 approver is not the current (step 2) approver

        $this->expectException(RuntimeException::class);
        app(ApprovalFlowService::class)
            ->sendBack($instance->id, $step1Approver->id, 'requester', 'มีเหตุผล');
    }

    public function test_send_back_blocked_on_closed_instance(): void
    {
        [$instance, , $step2Approver] = $this->makeTwoStepInstanceAtStep2();
        $instance->update(['status' => 'approved']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already closed');
        app(ApprovalFlowService::class)
            ->sendBack($instance->id, $step2Approver->id, 'requester', 'มีเหตุผล');
    }

    // ---------- HTTP / controller ----------

    public function test_endpoint_send_back_to_requester_returns_submission_to_draft(): void
    {
        [$submission, $approver] = $this->makeSubmissionAwaitingApprover();

        $response = $this->actingAsWebSession($approver)
            ->post(route('forms.submission.send-back', $submission), [
                'destination' => 'requester',
                'comment' => 'กรุณาแก้ไขข้อมูลผู้ติดต่อ',
            ]);

        $response->assertRedirect(route('approvals.my'));
        $this->assertSame('draft', $submission->fresh()->status);
        $this->assertSame('returned', $submission->fresh()->instance->status);
        $this->assertDatabaseHas('submission_activity_log', [
            'submission_id' => $submission->id,
            'action' => 'sent_back',
        ]);
    }

    public function test_endpoint_send_back_requires_comment(): void
    {
        [$submission, $approver] = $this->makeSubmissionAwaitingApprover();

        $response = $this->actingAsWebSession($approver)
            ->post(route('forms.submission.send-back', $submission), [
                'destination' => 'requester',
            ]);

        $response->assertSessionHasErrors('comment');
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    // ---------- helpers ----------

    private static int $seq = 0;

    private function makeUser(string $tag): User
    {
        self::$seq++;

        return User::query()->create([
            'first_name' => 'SB',
            'last_name' => $tag.self::$seq,
            'email' => "sb_{$tag}_".self::$seq.'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    /**
     * Two-step workflow instance currently sitting on step 2 (step 1 already
     * approved). Returns [instance, step1Approver, step2Approver].
     *
     * @return array{0: ApprovalInstance, 1: User, 2: User}
     */
    private function makeTwoStepInstanceAtStep2(): array
    {
        $requester = $this->makeUser('req');
        $step1Approver = $this->makeUser('appr1');
        $step2Approver = $this->makeUser('appr2');

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'SB WF '.self::$seq,
            'document_type' => 'sendback_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        foreach ([[1, $step1Approver], [2, $step2Approver]] as [$stepNo, $approver]) {
            ApprovalWorkflowStage::query()->create([
                'workflow_id' => $workflow->id,
                'step_no' => $stepNo,
                'name' => 'Stage '.$stepNo,
                'approver_type' => 'user',
                'approver_ref' => (string) $approver->id,
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'sendback_test',
            'reference_no' => 'SB-'.self::$seq,
            'payload' => [],
            'current_step_no' => 2,
            'status' => 'pending',
        ]);

        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Stage 1',
            'approver_type' => 'user',
            'approver_ref' => (string) $step1Approver->id,
            'min_approvals' => 1,
            'approved_by' => [['user_id' => $step1Approver->id, 'name' => 'SB appr1', 'at' => now()->toIso8601String()]],
            'acted_by_user_id' => $step1Approver->id,
            'action' => 'approved',
        ]);
        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 2,
            'stage_name' => 'Stage 2',
            'approver_type' => 'user',
            'approver_ref' => (string) $step2Approver->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        return [$instance->fresh(['steps', 'workflow']), $step1Approver, $step2Approver];
    }

    /**
     * One-step workflow instance on step 1. Returns [instance, approver].
     *
     * @return array{0: ApprovalInstance, 1: User}
     */
    private function makeSingleStepInstance(): array
    {
        $requester = $this->makeUser('req');
        $approver = $this->makeUser('appr');

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'SB single '.self::$seq,
            'document_type' => 'sendback_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Stage 1',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'sendback_test',
            'reference_no' => 'SB1-'.self::$seq,
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);
        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Stage 1',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        return [$instance->fresh(['steps', 'workflow']), $approver];
    }

    /**
     * A submitted DocumentForm submission linked to a one-step approval
     * instance, plus an approver who holds approval.approve.
     *
     * @return array{0: DocumentFormSubmission, 1: User}
     */
    private function makeSubmissionAwaitingApprover(): array
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        [$instance, $approver] = $this->makeSingleStepInstance();
        $approver->givePermissionTo('approval.approve');

        $form = DocumentForm::factory()->create();

        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $instance->requester_user_id,
            'payload' => [],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        return [$submission, $approver];
    }
}
