<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
use App\Models\Position;
use App\Models\User;
use App\Notifications\ApprovalEscalationReminder;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EscalationReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => 'password',
            'is_active'  => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeWorkflow(string $docType = 'repair_request'): ApprovalWorkflow
    {
        return ApprovalWorkflow::create([
            'name'          => 'Escalation WF ' . uniqid(),
            'document_type' => $docType,
            'is_active'     => true,
        ]);
    }

    private function makeInstance(ApprovalWorkflow $wf, User $requester, int $stepNo = 1): ApprovalInstance
    {
        return ApprovalInstance::create([
            'workflow_id'       => $wf->id,
            'department_id'     => null,
            'requester_user_id' => $requester->id,
            'document_type'     => $wf->document_type,
            'reference_no'      => 'ESC-' . uniqid(),
            'payload'           => [],
            'current_step_no'   => $stepNo,
            'status'            => 'pending',
        ]);
    }

    private function makeStep(
        ApprovalInstance $instance,
        string $approverType,
        string $approverRef,
        int $escalationDays = 2,
        ?string $action = 'pending'
    ): ApprovalInstanceStep {
        return ApprovalInstanceStep::create([
            'approval_instance_id' => $instance->id,
            'step_no'              => $instance->current_step_no,
            'stage_name'           => 'Test Stage',
            'approver_type'        => $approverType,
            'approver_ref'         => $approverRef,
            'min_approvals'        => 1,
            'escalation_after_days' => $escalationDays,
            'approved_by'          => [],
            'action'               => $action,
        ]);
    }

    // ──────────────────────────────────────────────────────────────

    public function test_command_skips_when_no_overdue_steps(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc1@example.com');
        $approver  = $this->makeUser('app-esc1@example.com');
        $wf        = $this->makeWorkflow();
        $instance  = $this->makeInstance($wf, $requester);

        // Step created just now — NOT yet past the 2-day threshold
        $this->makeStep($instance, 'user', (string) $approver->id, 2);

        $this->artisan('approval:send-escalation-reminders')
            ->expectsOutput('Escalation reminders sent for 0 step(s).')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_notifies_user_approver_when_overdue(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc2@example.com');
        $approver  = $this->makeUser('app-esc2@example.com');
        $wf        = $this->makeWorkflow();
        $instance  = $this->makeInstance($wf, $requester);

        $step = $this->makeStep($instance, 'user', (string) $approver->id, 2);
        // Backdate: created 3 days ago (threshold is 2)
        $step->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('approval:send-escalation-reminders')
            ->assertSuccessful();

        Notification::assertSentTo($approver, ApprovalEscalationReminder::class);
    }

    public function test_command_sets_escalation_notified_at_after_sending(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc3@example.com');
        $approver  = $this->makeUser('app-esc3@example.com');
        $wf        = $this->makeWorkflow();
        $instance  = $this->makeInstance($wf, $requester);

        $step = $this->makeStep($instance, 'user', (string) $approver->id, 2);
        $step->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('approval:send-escalation-reminders')->assertSuccessful();

        $this->assertNotNull($step->fresh()->escalation_notified_at);
    }

    public function test_command_skips_already_notified_step(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc4@example.com');
        $approver  = $this->makeUser('app-esc4@example.com');
        $wf        = $this->makeWorkflow();
        $instance  = $this->makeInstance($wf, $requester);

        $step = $this->makeStep($instance, 'user', (string) $approver->id, 2);
        $step->forceFill([
            'created_at'             => now()->subDays(3),
            'escalation_notified_at' => now()->subDay(),
        ])->save();

        $this->artisan('approval:send-escalation-reminders')
            ->expectsOutput('Escalation reminders sent for 0 step(s).')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_skips_non_pending_step(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc5@example.com');
        $approver  = $this->makeUser('app-esc5@example.com');
        $wf        = $this->makeWorkflow();
        $instance  = $this->makeInstance($wf, $requester);

        // action = 'approved', not 'pending'
        $step = $this->makeStep($instance, 'user', (string) $approver->id, 2, 'approved');
        $step->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('approval:send-escalation-reminders')
            ->expectsOutput('Escalation reminders sent for 0 step(s).')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_skips_when_not_the_current_step(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc6@example.com');
        $approver  = $this->makeUser('app-esc6@example.com');
        $wf        = $this->makeWorkflow();
        // Instance is on step 2
        $instance  = $this->makeInstance($wf, $requester, stepNo: 2);

        // But step has step_no=1 (not current)
        $step = ApprovalInstanceStep::create([
            'approval_instance_id' => $instance->id,
            'step_no'              => 1,
            'stage_name'           => 'Step 1',
            'approver_type'        => 'user',
            'approver_ref'         => (string) $approver->id,
            'min_approvals'        => 1,
            'escalation_after_days' => 2,
            'approved_by'          => [],
            'action'               => 'pending',
        ]);
        $step->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('approval:send-escalation-reminders')
            ->expectsOutput('Escalation reminders sent for 0 step(s).')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_command_notifies_all_users_in_position(): void
    {
        Notification::fake();

        $requester = $this->makeUser('req-esc7@example.com');
        $pos = Position::create(['name' => 'Test Position', 'code' => 'POS-TEST', 'is_active' => true]);
        $u1  = $this->makeUser('pos-u1@example.com');
        $u2  = $this->makeUser('pos-u2@example.com');
        $u1->update(['position_id' => $pos->id]);
        $u2->update(['position_id' => $pos->id]);

        $wf       = $this->makeWorkflow();
        $instance = $this->makeInstance($wf, $requester);

        $step = $this->makeStep($instance, 'position', (string) $pos->id, 2);
        $step->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('approval:send-escalation-reminders')->assertSuccessful();

        Notification::assertSentTo($u1, ApprovalEscalationReminder::class);
        Notification::assertSentTo($u2, ApprovalEscalationReminder::class);
    }

    public function test_escalation_after_days_propagated_from_stage_to_step(): void
    {
        $requester = $this->makeUser('req-esc8@example.com');
        $approver  = $this->makeUser('app-esc8@example.com');

        $docType = 'escalation_prop_' . uniqid();
        $wf = $this->makeWorkflow($docType);
        ApprovalWorkflowStage::create([
            'workflow_id'           => $wf->id,
            'step_no'               => 1,
            'name'                  => 'Stage',
            'approver_type'         => 'user',
            'approver_ref'          => (string) $approver->id,
            'min_approvals'         => 1,
            'escalation_after_days' => 5,
            'is_active'             => true,
        ]);

        // Bind the workflow to the document type so start() can find it
        $dept = Department::create(['name' => 'Test Dept', 'code' => 'DEPT-ESC', 'is_active' => true]);
        DepartmentWorkflowBinding::create([
            'department_id' => $dept->id,
            'document_type' => $docType,
            'workflow_id'   => $wf->id,
        ]);

        // start() via service propagates escalation_after_days to instance step
        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            departmentId: null,
            requesterUserId: $requester->id,
            referenceNo: 'ESC-PROP-' . uniqid(),
        );

        $step = $instance->steps->first();
        $this->assertSame(5, $step->escalation_after_days);
    }
}
