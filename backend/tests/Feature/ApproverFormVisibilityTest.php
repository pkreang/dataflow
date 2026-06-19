<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Coverage for the approver-visibility feature:
 *  - ApprovalInstance::scopePendingForApprover (the predicate shared by
 *    /approvals/my, the sidebar badge, and the form-list merge)
 *  - DocumentFormSubmissionController::listByForm now surfaces submissions the
 *    viewer must approve (not only ones they own) with an "awaiting my approval"
 *    marker, while hiding others' submissions from unrelated users.
 */
class ApproverFormVisibilityTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    private static int $seq = 0;

    public function test_pending_for_approver_scope_matches_approver_and_excludes_requester(): void
    {
        [, $form, $approver, $requester] = $this->makeSubmissionAwaitingApprover();

        // Approver sees exactly the one pending instance.
        $this->assertSame(1, ApprovalInstance::query()
            ->pendingForApprover($approver->id, [], null)->count());

        // Requester does NOT see their own document (allow_requester_as_approver = false).
        $this->assertSame(0, ApprovalInstance::query()
            ->pendingForApprover($requester->id, [], null)->count());

        unset($form);
    }

    public function test_form_list_shows_submission_awaiting_my_approval_to_approver(): void
    {
        [$submission, $form, $approver] = $this->makeSubmissionAwaitingApprover();

        $response = $this->actingAsWebSession($approver)
            ->get(route('forms.list-by-form', $form));

        $response->assertOk();
        $response->assertSee($submission->reference_no);
    }

    public function test_form_list_hides_others_submissions_from_unrelated_user(): void
    {
        [$submission, $form] = $this->makeSubmissionAwaitingApprover();

        $stranger = $this->makeUser('stranger');

        $response = $this->actingAsWebSession($stranger)
            ->get(route('forms.list-by-form', $form));

        $response->assertOk();
        $response->assertDontSee($submission->reference_no);
    }

    public function test_form_list_drops_restricted_searchable_columns_for_cross_org_unit_approver(): void
    {
        [$submission, $form, $approver] = $this->makeSubmissionAwaitingApprover();

        // Approver belongs to a real org_unit; the restricted field is visible
        // only to a DIFFERENT org_unit, so we exercise the in_array mismatch
        // (not just the null-org short-circuit).
        $approverOrg = OrgUnit::create(['name' => 'Approver Org', 'type' => 'department', 'is_active' => true]);
        $otherOrg = OrgUnit::create(['name' => 'Other Org', 'type' => 'department', 'is_active' => true]);
        $approver->forceFill(['org_unit_id' => $approverOrg->id])->save();

        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'secret', 'label' => 'Secret',
            'field_type' => 'text', 'sort_order' => 1, 'is_searchable' => true,
            'visible_to_org_units' => [$otherOrg->id],
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'note', 'label' => 'Note',
            'field_type' => 'text', 'sort_order' => 2, 'is_searchable' => true,
            'visible_to_org_units' => [],
        ]);

        $submission->forceFill(['payload' => [
            'secret' => 'TOPSECRETVALUE',
            'note' => 'PUBLICOKVALUE',
        ]])->save();

        $response = $this->actingAsWebSession($approver)
            ->get(route('forms.list-by-form', $form));

        $response->assertOk();
        // Positive control: an unrestricted searchable column still renders, so a
        // pass can't come from the filter nuking every column.
        $response->assertSee('PUBLICOKVALUE');
        // The genuinely new exposure: a restricted searchable value must NOT leak
        // into the list columns for a cross-org-unit approver.
        $response->assertDontSee('TOPSECRETVALUE');
    }

    public function test_primary_approver_of_multi_source_step_sees_instance(): void
    {
        $posPrimary = Position::query()->create(['name' => 'PosP'.self::$seq, 'code' => 'PP'.self::$seq]);
        $posAnd = Position::query()->create(['name' => 'PosA'.self::$seq, 'code' => 'PA'.self::$seq]);

        $requester = $this->makeUser('req-ms');
        $primaryApprover = $this->makeUser('appr-primary');
        $primaryApprover->forceFill(['position_id' => $posPrimary->id])->save();

        $instance = $this->makeMultiSourceInstance($requester, primaryPositionId: $posPrimary->id, andSourcePositionId: $posAnd->id);

        $count = ApprovalInstance::query()
            ->pendingForApprover($primaryApprover->id, [], $posPrimary->id)
            ->count();

        $this->assertSame(1, $count, 'primary approver must see multi-source step');
    }

    public function test_and_source_approver_of_multi_source_step_sees_instance(): void
    {
        $posPrimary = Position::query()->create(['name' => 'PosP2'.self::$seq, 'code' => 'PP2'.self::$seq]);
        $posAnd = Position::query()->create(['name' => 'PosA2'.self::$seq, 'code' => 'PA2'.self::$seq]);

        $requester = $this->makeUser('req-as');
        $andSourceApprover = $this->makeUser('appr-and');
        $andSourceApprover->forceFill(['position_id' => $posAnd->id])->save();

        $instance = $this->makeMultiSourceInstance($requester, primaryPositionId: $posPrimary->id, andSourcePositionId: $posAnd->id);

        $count = ApprovalInstance::query()
            ->pendingForApprover($andSourceApprover->id, [], $posAnd->id)
            ->count();

        $this->assertSame(1, $count, 'AND-source approver must see multi-source step');

        unset($instance);
    }

    // ---------- helpers ----------

    private function makeUser(string $tag): User
    {
        self::$seq++;

        return User::query()->create([
            'first_name' => 'AV',
            'last_name' => $tag.self::$seq,
            'email' => "av_{$tag}_".self::$seq.'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeMultiSourceInstance(User $requester, int $primaryPositionId, int $andSourcePositionId): ApprovalInstance
    {
        self::$seq++;

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'MS WF '.self::$seq,
            'document_type' => 'ms_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $requester->id,
            'document_type' => 'ms_test',
            'reference_no' => 'MS-'.self::$seq,
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);

        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Quorum Stage',
            'approver_type' => 'position',
            'approver_ref' => (string) $primaryPositionId,
            'approver_rules' => [['type' => 'position', 'ref' => (string) $andSourcePositionId, 'min_count' => 1]],
            'min_approvals' => 2,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        return $instance;
    }

    /**
     * A submitted DocumentForm submission linked to a one-step approval instance,
     * plus the approver (holding approval.approve) and the requester.
     *
     * @return array{0: DocumentFormSubmission, 1: DocumentForm, 2: User, 3: User}
     */
    private function makeSubmissionAwaitingApprover(): array
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $requester = $this->makeUser('req');
        $approver = $this->makeUser('appr');
        $approver->givePermissionTo('approval.approve');

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'AV WF '.self::$seq,
            'document_type' => 'av_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $requester->id,
            'document_type' => 'av_test',
            'reference_no' => 'AV-'.self::$seq,
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

        $form = DocumentForm::factory()->create();

        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => [],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        return [$submission, $form, $approver, $requester];
    }
}
