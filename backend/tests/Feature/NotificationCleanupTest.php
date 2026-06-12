<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use App\Notifications\ApprovalPendingNotification;
use App\Notifications\WorkflowApprovedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Acting on (or viewing) a document must clear its stale bell notifications —
 * users approve from the list pages, not by clicking the notification, so
 * "รอการอนุมัติ" entries used to stay unread forever.
 */
class NotificationCleanupTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_acting_marks_actor_pending_notification_read(): void
    {
        [$instance, $approver] = $this->makePendingInstance();
        $other = $this->makePendingInstance()[0]; // unrelated instance

        $n1 = $this->makeDbNotification($approver, ApprovalPendingNotification::class, $instance->id);
        $n2 = $this->makeDbNotification($approver, ApprovalPendingNotification::class, $other->id);

        app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved');

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($n2->fresh()->read_at, 'unrelated instance notification must stay unread');
    }

    public function test_completed_instance_clears_other_recipients_too(): void
    {
        [$instance, $approver] = $this->makePendingInstance();
        $bystander = $this->makeRegularUser('ncl-bystander-'.uniqid().'@example.test');

        // e.g. another position-holder who also received the pending alert
        $n = $this->makeDbNotification($bystander, ApprovalPendingNotification::class, $instance->id);

        app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved');

        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mid_flow_act_does_not_clear_next_step_recipients(): void
    {
        [$instance, $approver1, $approver2] = $this->makePendingInstance(steps: 2);

        $mine = $this->makeDbNotification($approver1, ApprovalPendingNotification::class, $instance->id);

        app(ApprovalFlowService::class)->act($instance->id, $approver1->id, 'approved');
        $this->assertSame('pending', $instance->fresh()->status);
        $this->assertNotNull($mine->fresh()->read_at);

        // step-2 approver's alert (arrives after step 1) must stay unread
        $next = $this->makeDbNotification($approver2, ApprovalPendingNotification::class, $instance->id);
        $this->assertNull($next->fresh()->read_at);
    }

    public function test_viewing_submission_marks_viewer_notifications_read(): void
    {
        [$instance, $approver] = $this->makePendingInstance();
        app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved');

        $requester = User::find($instance->requester_user_id);
        $form = DocumentForm::factory()->create();
        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => [],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
        ]);

        $mine = $this->makeDbNotification($requester, WorkflowApprovedNotification::class, $instance->id);
        $theirs = $this->makeDbNotification($approver, WorkflowApprovedNotification::class, $instance->id);

        $this->actingAsWebSession($requester)
            ->get(route('forms.submission.show', $submission))
            ->assertOk();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at, 'other users keep their own unread state');
    }

    public function test_notifications_index_unread_filter(): void
    {
        $user = $this->makeRegularUser('ncl-bell-'.uniqid().'@example.test');
        $unread = $this->makeDbNotification($user, ApprovalPendingNotification::class, 991);
        $read = $this->makeDbNotification($user, ApprovalPendingNotification::class, 992);
        $read->markAsRead();

        $response = $this->actingAsWebSession($user)
            ->getJson(route('notifications.index', ['unread' => 1]))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($unread->id));
        $this->assertFalse($ids->contains($read->id));

        // Without the filter the full history still comes back.
        $all = $this->actingAsWebSession($user)
            ->getJson(route('notifications.index'))
            ->assertOk();
        $this->assertCount(2, $all->json('data'));
    }

    // ── Helpers ─────────────────────────────────────────────

    /** @return array{0: ApprovalInstance, 1: User, 2?: User} */
    private function makePendingInstance(int $steps = 1): array
    {
        $requester = $this->makeRegularUser('ncl-req-'.uniqid().'@example.test');
        $approvers = [];

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'NCL WF '.uniqid(),
            'document_type' => 'ncl_test',
            'is_active' => true,
        ]);
        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $requester->id,
            'document_type' => 'ncl_test',
            'reference_no' => 'NCL-'.uniqid(),
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);
        for ($i = 1; $i <= $steps; $i++) {
            $approver = $this->makeRegularUser("ncl-appr{$i}-".uniqid().'@example.test');
            $approvers[] = $approver;
            ApprovalInstanceStep::query()->create([
                'approval_instance_id' => $instance->id,
                'step_no' => $i,
                'stage_name' => "Stage {$i}",
                'approver_type' => 'user',
                'approver_ref' => (string) $approver->id,
                'min_approvals' => 1,
                'approved_by' => [],
                'action' => 'pending',
            ]);
        }

        return [$instance, ...$approvers];
    }

    private function makeDbNotification(User $user, string $type, int $instanceId)
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => ['title' => 't', 'body' => 'b', 'instance_id' => $instanceId],
            'read_at' => null,
        ]);
    }
}
