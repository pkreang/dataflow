<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\Position;
use App\Models\User;
use App\Models\UserSubstitution;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class SubstitutionApprovalTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    private function makeUser(string $email): User
    {
        return User::create([
            'first_name' => 'Sub',
            'last_name' => 'Test',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeSub(
        User $from,
        User $to,
        bool $isActive = true,
        ?string $startsAt = null,
        ?string $endsAt = null
    ): UserSubstitution {
        return UserSubstitution::create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'is_active' => $isActive,
            'starts_at' => $startsAt ?? now()->subDay()->toDateTimeString(),
            'ends_at' => $endsAt,
        ]);
    }

    private function makeWorkflowAndInstance(User $requester, User $approver): array
    {
        $wf = ApprovalWorkflow::create([
            'name' => 'Sub WF '.uniqid(),
            'document_type' => 'repair_request',
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::create([
            'workflow_id' => $wf->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'repair_request',
            'reference_no' => 'SUB-'.uniqid(),
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);

        $step = ApprovalInstanceStep::create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Approval',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        $instance->load('workflow');

        return [$instance, $step];
    }

    // ──────────────────────────────────────────────────────────────
    // UserSubstitution model tests
    // ──────────────────────────────────────────────────────────────

    public function test_active_substitute_for_returns_true_in_date_range(): void
    {
        $from = $this->makeUser('from1@example.com');
        $to = $this->makeUser('to1@example.com');

        $this->makeSub($from, $to, true,
            now()->subDay()->toDateTimeString(),
            now()->addDay()->toDateTimeString()
        );

        $this->assertTrue(UserSubstitution::activeSubstituteFor($from->id, $to->id, now()));
    }

    public function test_active_substitute_for_returns_false_when_inactive(): void
    {
        $from = $this->makeUser('from2@example.com');
        $to = $this->makeUser('to2@example.com');

        $this->makeSub($from, $to, false,
            now()->subDay()->toDateTimeString(),
            now()->addDay()->toDateTimeString()
        );

        $this->assertFalse(UserSubstitution::activeSubstituteFor($from->id, $to->id, now()));
    }

    public function test_active_substitute_for_returns_false_before_starts_at(): void
    {
        $from = $this->makeUser('from3@example.com');
        $to = $this->makeUser('to3@example.com');

        $this->makeSub($from, $to, true,
            now()->addDay()->toDateTimeString(),  // starts tomorrow
            now()->addDays(5)->toDateTimeString()
        );

        $this->assertFalse(UserSubstitution::activeSubstituteFor($from->id, $to->id, now()));
    }

    public function test_active_substitute_for_returns_false_after_ends_at(): void
    {
        $from = $this->makeUser('from4@example.com');
        $to = $this->makeUser('to4@example.com');

        $this->makeSub($from, $to, true,
            now()->subDays(5)->toDateTimeString(),
            now()->subDay()->toDateTimeString()  // ended yesterday
        );

        $this->assertFalse(UserSubstitution::activeSubstituteFor($from->id, $to->id, now()));
    }

    public function test_active_substitute_for_returns_true_when_ends_at_null(): void
    {
        $from = $this->makeUser('from5@example.com');
        $to = $this->makeUser('to5@example.com');

        $this->makeSub($from, $to, true,
            now()->subDay()->toDateTimeString(),
            null  // open-ended
        );

        $this->assertTrue(UserSubstitution::activeSubstituteFor($from->id, $to->id, now()));
    }

    public function test_find_active_substitute_returns_to_user_id(): void
    {
        $from = $this->makeUser('from6@example.com');
        $to = $this->makeUser('to6@example.com');

        $this->makeSub($from, $to, true, now()->subDay()->toDateTimeString(), null);

        $result = UserSubstitution::findActiveSubstitute($from->id, now());
        $this->assertSame($to->id, $result);
    }

    public function test_find_active_substitute_returns_null_when_none(): void
    {
        $from = $this->makeUser('from7@example.com');

        $result = UserSubstitution::findActiveSubstitute($from->id, now());
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────────────────
    // canUserActOnStep integration tests
    // ──────────────────────────────────────────────────────────────

    public function test_substitute_can_act_on_user_type_step(): void
    {
        $requester = $this->makeUser('req-sub8@example.com');
        $primary = $this->makeUser('primary-sub8@example.com');
        $substitute = $this->makeUser('sub-sub8@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance, $step] = $this->makeWorkflowAndInstance($requester, $primary);

        $svc = app(ApprovalFlowService::class);
        $this->assertTrue($svc->canUserActOnStep($instance, $step, $substitute->id));
    }

    public function test_substitute_cannot_act_on_position_type_step(): void
    {
        $requester = $this->makeUser('req-sub9@example.com');
        $primary = $this->makeUser('primary-sub9@example.com');
        $substitute = $this->makeUser('sub-sub9@example.com');
        $pos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'is_active' => true]);
        $primary->update(['position_id' => $pos->id]);

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        $wf = ApprovalWorkflow::create([
            'name' => 'POS WF', 'document_type' => 'repair_request', 'is_active' => true,
        ]);
        $instance = ApprovalInstance::create([
            'workflow_id' => $wf->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'repair_request',
            'reference_no' => 'POS-'.uniqid(),
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);
        $step = ApprovalInstanceStep::create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Manager',
            'approver_type' => 'position',
            'approver_ref' => (string) $pos->id,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);
        $instance->load('workflow');

        $svc = app(ApprovalFlowService::class);
        // substitute doesn't have the position → cannot act
        $this->assertFalse($svc->canUserActOnStep($instance, $step, $substitute->id));
    }

    public function test_primary_approver_still_can_act_with_substitution_active(): void
    {
        $requester = $this->makeUser('req-sub10@example.com');
        $primary = $this->makeUser('primary-sub10@example.com');
        $substitute = $this->makeUser('sub-sub10@example.com');

        // Substitution is active, but primary should still be able to act
        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance, $step] = $this->makeWorkflowAndInstance($requester, $primary);

        $svc = app(ApprovalFlowService::class);
        $this->assertTrue($svc->canUserActOnStep($instance, $step, $primary->id));
    }

    public function test_wrong_substitute_cannot_act(): void
    {
        $requester = $this->makeUser('req-sub11@example.com');
        $primary = $this->makeUser('primary-sub11@example.com');
        $substitute = $this->makeUser('sub-sub11@example.com');
        $wrongUser = $this->makeUser('wrong-sub11@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance, $step] = $this->makeWorkflowAndInstance($requester, $primary);

        $svc = app(ApprovalFlowService::class);
        // wrongUser is not the substitute of primary → false
        $this->assertFalse($svc->canUserActOnStep($instance, $step, $wrongUser->id));
    }

    public function test_no_substitution_active_means_only_primary_can_act(): void
    {
        $requester = $this->makeUser('req-sub12@example.com');
        $primary = $this->makeUser('primary-sub12@example.com');
        $other = $this->makeUser('other-sub12@example.com');

        // No substitution created
        [$instance, $step] = $this->makeWorkflowAndInstance($requester, $primary);

        $svc = app(ApprovalFlowService::class);
        $this->assertTrue($svc->canUserActOnStep($instance, $step, $primary->id));
        $this->assertFalse($svc->canUserActOnStep($instance, $step, $other->id));
    }

    // ──────────────────────────────────────────────────────────────
    // pendingForApprover list visibility tests
    // ──────────────────────────────────────────────────────────────

    public function test_substitute_sees_pending_approval_in_list(): void
    {
        $requester = $this->makeUser('req-sub13@example.com');
        $primary = $this->makeUser('primary-sub13@example.com');
        $substitute = $this->makeUser('sub-sub13@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance] = $this->makeWorkflowAndInstance($requester, $primary);

        $ids = ApprovalInstance::query()
            ->pendingForApprover($substitute->id, [], null)
            ->pluck('id')->all();

        $this->assertContains($instance->id, $ids);
    }

    public function test_substitute_does_not_see_list_when_substitution_inactive(): void
    {
        $requester = $this->makeUser('req-sub14@example.com');
        $primary = $this->makeUser('primary-sub14@example.com');
        $substitute = $this->makeUser('sub-sub14@example.com');

        $this->makeSub($primary, $substitute, false, now()->subDay()->toDateTimeString(), null);

        $this->makeWorkflowAndInstance($requester, $primary);

        $this->assertSame(0, ApprovalInstance::query()
            ->pendingForApprover($substitute->id, [], null)->count());
    }

    public function test_substitute_does_not_see_list_when_substitution_expired(): void
    {
        $requester = $this->makeUser('req-sub15@example.com');
        $primary = $this->makeUser('primary-sub15@example.com');
        $substitute = $this->makeUser('sub-sub15@example.com');

        $this->makeSub($primary, $substitute, true,
            now()->subDays(5)->toDateTimeString(),
            now()->subDay()->toDateTimeString()  // ended yesterday
        );

        $this->makeWorkflowAndInstance($requester, $primary);

        $this->assertSame(0, ApprovalInstance::query()
            ->pendingForApprover($substitute->id, [], null)->count());
    }

    public function test_substitute_who_is_requester_does_not_see_own_request(): void
    {
        $primary = $this->makeUser('primary-sub16@example.com');
        $substitute = $this->makeUser('sub-sub16@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        // substitute ยื่นเอง โดย primary เป็น approver → ห้ามเห็นใบของตัวเอง
        // (requester-exclusion ทำงานเมื่อ workflow ไม่อนุญาต requester เป็น approver)
        [$instance] = $this->makeWorkflowAndInstance($substitute, $primary);
        $instance->workflow->update(['allow_requester_as_approver' => false]);

        $this->assertSame(0, ApprovalInstance::query()
            ->pendingForApprover($substitute->id, [], null)->count());
    }

    public function test_primary_still_sees_pending_approval_with_substitution_active(): void
    {
        $requester = $this->makeUser('req-sub17@example.com');
        $primary = $this->makeUser('primary-sub17@example.com');
        $substitute = $this->makeUser('sub-sub17@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance] = $this->makeWorkflowAndInstance($requester, $primary);

        $ids = ApprovalInstance::query()
            ->pendingForApprover($primary->id, [], null)
            ->pluck('id')->all();

        $this->assertContains($instance->id, $ids);
    }

    // ──────────────────────────────────────────────────────────────
    // submission detail view authorization tests
    // ──────────────────────────────────────────────────────────────

    private function makeSubmission(User $requester, ApprovalInstance $instance): DocumentFormSubmission
    {
        $form = DocumentForm::factory()->create(['document_type' => 'repair_request']);

        return DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => [],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);
    }

    public function test_substitute_can_view_submission_detail(): void
    {
        $requester = $this->makeUser('req-sub18@example.com');
        $primary = $this->makeUser('primary-sub18@example.com');
        $substitute = $this->makeUser('sub-sub18@example.com');

        $this->makeSub($primary, $substitute, true, now()->subDay()->toDateTimeString(), null);

        [$instance] = $this->makeWorkflowAndInstance($requester, $primary);
        $submission = $this->makeSubmission($requester, $instance);

        $this->actingAsWebSession($substitute)
            ->get(route('forms.submission.show', $submission))
            ->assertOk();
    }

    public function test_unrelated_user_cannot_view_submission_detail(): void
    {
        $requester = $this->makeUser('req-sub19@example.com');
        $primary = $this->makeUser('primary-sub19@example.com');
        $stranger = $this->makeUser('stranger-sub19@example.com');

        [$instance] = $this->makeWorkflowAndInstance($requester, $primary);
        $submission = $this->makeSubmission($requester, $instance);

        $this->actingAsWebSession($stranger)
            ->get(route('forms.submission.show', $submission))
            ->assertForbidden();
    }

    public function test_expired_substitute_cannot_view_submission_detail(): void
    {
        $requester = $this->makeUser('req-sub20@example.com');
        $primary = $this->makeUser('primary-sub20@example.com');
        $substitute = $this->makeUser('sub-sub20@example.com');

        $this->makeSub($primary, $substitute, true,
            now()->subDays(5)->toDateTimeString(),
            now()->subDay()->toDateTimeString()
        );

        [$instance] = $this->makeWorkflowAndInstance($requester, $primary);
        $submission = $this->makeSubmission($requester, $instance);

        $this->actingAsWebSession($substitute)
            ->get(route('forms.submission.show', $submission))
            ->assertForbidden();
    }
}
