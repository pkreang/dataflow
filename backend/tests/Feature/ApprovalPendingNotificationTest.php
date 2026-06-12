<?php

namespace Tests\Feature;

use App\Events\Approval\WorkflowStarted;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\User;
use App\Models\UserSubstitution;
use App\Notifications\ApprovalPendingNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Pending-approval bell delivery. Regression focus: the listener's 2-minute
 * dedup guard used to match ANY notification for the instance+step — the
 * inline substitute notify inside start() therefore suppressed the PRIMARY
 * approver's notification whenever an active substitution existed.
 */
class ApprovalPendingNotificationTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_primary_approver_gets_pending_notification(): void
    {
        [$svc, $form, $requester, $approver] = $this->makeScenario();

        $svc->start('apn_test', null, $requester->id, formKey: $form->form_key);

        $this->assertSame(1, $this->pendingCountFor($approver));
    }

    public function test_substitution_notifies_both_primary_and_substitute(): void
    {
        [$svc, $form, $requester, $approver] = $this->makeScenario();
        $substitute = $this->makeRegularUser('apn-sub-'.uniqid().'@example.test');
        UserSubstitution::create([
            'from_user_id' => $approver->id,
            'to_user_id' => $substitute->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
        ]);

        $svc->start('apn_test', null, $requester->id, formKey: $form->form_key);

        $this->assertSame(1, $this->pendingCountFor($substitute), 'substitute must be notified');
        $this->assertSame(1, $this->pendingCountFor($approver), 'primary approver must ALSO be notified');
    }

    public function test_duplicate_event_does_not_double_notify(): void
    {
        [$svc, $form, $requester, $approver] = $this->makeScenario();

        $instance = $svc->start('apn_test', null, $requester->id, formKey: $form->form_key);

        // Same event fired again within the dedup window (e.g. listener retry)
        event(new WorkflowStarted($instance->load('steps')));

        $this->assertSame(1, $this->pendingCountFor($approver));
    }

    // ── Helpers ─────────────────────────────────────────────

    /** @return array{0: ApprovalFlowService, 1: DocumentForm, 2: User, 3: User} */
    private function makeScenario(): array
    {
        $requester = $this->makeRegularUser('apn-req-'.uniqid().'@example.test');
        $approver = $this->makeRegularUser('apn-appr-'.uniqid().'@example.test');

        $form = DocumentForm::factory()->create([
            'form_key' => 'apn_'.uniqid(),
            'document_type' => 'apn_test',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'APN WF '.uniqid(),
            'document_type' => 'apn_test',
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Step 1',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        DocumentFormWorkflowPolicy::query()->create([
            'form_id' => $form->id,
            'department_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        return [app(ApprovalFlowService::class), $form, $requester, $approver];
    }

    private function pendingCountFor(User $user): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('type', ApprovalPendingNotification::class)
            ->count();
    }
}
