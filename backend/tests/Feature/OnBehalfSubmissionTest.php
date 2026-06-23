<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\User;
use App\Notifications\WorkflowApprovedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * "Submit on behalf of" — a permission-gated creator (HR / secretary) files a
 * document owned by someone else. The submission belongs to the beneficiary
 * (user_id) while created_by_user_id records the actual author; all engine
 * behavior (routing, exclusion, overlap, notifications) follows the owner.
 */
class OnBehalfSubmissionTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::updateOrCreate(
            ['name' => 'submission.create_for_others', 'guard_name' => 'web'],
            ['module' => 'submission', 'action' => 'create_for_others']
        );
        Permission::updateOrCreate(
            ['name' => 'approval.approve', 'guard_name' => 'web'],
            ['module' => 'approval', 'action' => 'approve']
        );
    }

    public function test_param_ignored_without_permission(): void
    {
        $creator = $this->makeRegularUser('obh-noperm-'.uniqid().'@example.test');
        $other = $this->makeRegularUser('obh-other-'.uniqid().'@example.test');
        $form = $this->makeSimpleForm();

        $this->actingAsWebSession($creator)
            ->post(route('forms.draft.store', $form), [
                'fields' => ['title' => 'x'],
                'on_behalf_of_user_id' => $other->id,
            ])
            ->assertRedirect();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame($creator->id, (int) $submission->user_id);
        $this->assertNull($submission->created_by_user_id);
    }

    public function test_on_behalf_sets_owner_creator_and_assigned_editor(): void
    {
        [$creator, $owner, $form] = $this->makeOnBehalfActors();

        $this->actingAsWebSession($creator)
            ->post(route('forms.draft.store', $form), [
                'fields' => ['title' => 'leave for boss'],
                'on_behalf_of_user_id' => $owner->id,
            ])
            ->assertRedirect();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame($owner->id, (int) $submission->user_id);
        $this->assertSame($creator->id, (int) $submission->created_by_user_id);
        $this->assertTrue($submission->isAssignedEditor($creator->id));
        $this->assertTrue($submission->isOnBehalf());
    }

    public function test_creator_can_submit_draft_and_workflow_routes_by_owner(): void
    {
        [$creator, $owner, $form] = $this->makeOnBehalfActors();

        $ownerManager = $this->makeRegularUser('obh-owner-mgr-'.uniqid().'@example.test');
        $creatorManager = $this->makeRegularUser('obh-creator-mgr-'.uniqid().'@example.test');
        $owner->update(['manager_id' => $ownerManager->id]);
        $creator->update(['manager_id' => $creatorManager->id]);

        $this->bindDirectManagerWorkflow($form);

        $submission = $this->makeOnBehalfDraft($form, $owner, $creator);

        $this->actingAsWebSession($creator)
            ->post(route('forms.draft.submit', $submission))
            ->assertRedirect();

        $fresh = $submission->fresh();
        $this->assertSame('submitted', $fresh->status);

        $instance = $fresh->instance;
        $this->assertSame($owner->id, (int) $instance->requester_user_id);

        // direct_manager resolves from the OWNER's manager, not the creator's.
        $step1 = $instance->steps->firstWhere('step_no', 1);
        $this->assertSame((string) $ownerManager->id, (string) $step1->approver_ref);
    }

    public function test_other_assigned_editor_still_cannot_submit(): void
    {
        [$creator, $owner, $form] = $this->makeOnBehalfActors();
        $helper = $this->makeRegularUser('obh-helper-'.uniqid().'@example.test');

        $this->bindDirectManagerWorkflow($form);
        $owner->update(['manager_id' => $this->makeRegularUser('obh-mgr2-'.uniqid().'@example.test')->id]);

        $submission = $this->makeOnBehalfDraft($form, $owner, $creator);
        $submission->update(['assigned_editor_user_ids' => [$creator->id, $helper->id]]);

        $this->actingAsWebSession($helper)
            ->post(route('forms.draft.submit', $submission))
            ->assertForbidden();
    }

    public function test_workflow_outcome_notifies_owner_and_creator(): void
    {
        Notification::fake();

        [$creator, $owner, $form] = $this->makeOnBehalfActors();
        $approver = $this->makeRegularUser('obh-appr-'.uniqid().'@example.test');
        $approver->givePermissionTo('approval.approve');
        $owner->update(['manager_id' => $approver->id]);

        $this->bindDirectManagerWorkflow($form);

        $submission = $this->makeOnBehalfDraft($form, $owner, $creator);
        $this->actingAsWebSession($creator)
            ->post(route('forms.draft.submit', $submission))
            ->assertRedirect();

        app(ApprovalFlowService::class)->act(
            $submission->fresh()->approval_instance_id,
            $approver->id,
            'approved',
            null
        );

        Notification::assertSentTo($owner, WorkflowApprovedNotification::class);
        Notification::assertSentTo($creator, WorkflowApprovedNotification::class);
    }

    public function test_leave_overlap_checked_against_owner(): void
    {
        [$creator, $owner, $form] = $this->makeOnBehalfActors(withDates: true);
        $owner->update(['manager_id' => $this->makeRegularUser('obh-mgr3-'.uniqid().'@example.test')->id]);
        $this->bindDirectManagerWorkflow($form);

        // Owner already has an active leave covering the same dates.
        $existing = $this->makeOnBehalfDraft($form, $owner, $creator, [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-03',
        ]);
        $this->actingAsWebSession($creator)
            ->post(route('forms.draft.submit', $existing))
            ->assertRedirect();

        $second = $this->makeOnBehalfDraft($form, $owner, $creator, [
            'date_from' => '2026-09-02', 'date_to' => '2026-09-04',
        ]);

        $response = $this->actingAsWebSession($creator)
            ->post(route('forms.draft.submit', $second));

        $response->assertSessionHasErrors('submit');
        $this->assertSame('draft', $second->fresh()->status);
    }

    public function test_both_see_submission_in_form_list(): void
    {
        [$creator, $owner, $form] = $this->makeOnBehalfActors();

        $submission = $this->makeOnBehalfDraft($form, $owner, $creator, ['title' => 'OBH-VISIBLE']);

        // หน้ารวม my-submissions ถูกตัดออกแล้ว — owner (user_id) + creator
        // (assigned_editor) ต้องยังเห็นใบในรายการของฟอร์มนั้น (list-by-form)
        foreach ([$owner, $creator] as $viewer) {
            $this->actingAsWebSession($viewer)
                ->get(route('forms.list-by-form', $form))
                ->assertOk()
                ->assertSee('#'.$submission->id);
        }
    }

    // ── Helpers ─────────────────────────────────────────────

    /** @return array{0: User, 1: User, 2: DocumentForm} */
    private function makeOnBehalfActors(bool $withDates = false): array
    {
        $creator = $this->makeRegularUser('obh-creator-'.uniqid().'@example.test');
        $creator->givePermissionTo('submission.create_for_others');
        $owner = $this->makeRegularUser('obh-owner-'.uniqid().'@example.test');

        return [$creator, $owner, $this->makeSimpleForm($withDates)];
    }

    private function makeSimpleForm(bool $withDates = false): DocumentForm
    {
        $form = DocumentForm::factory()->create([
            'document_type' => 'obh_test_'.uniqid(),
            'is_active' => true,
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);
        if ($withDates) {
            foreach (['date_from', 'date_to'] as $i => $key) {
                DocumentFormField::query()->create([
                    'form_id' => $form->id, 'field_key' => $key, 'label' => $key,
                    'field_type' => 'date', 'sort_order' => $i + 2, 'editable_by' => ['requester'],
                ]);
            }
        }

        return $form->fresh('fields');
    }

    private function bindDirectManagerWorkflow(DocumentForm $form): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'OBH WF '.uniqid(),
            'document_type' => $form->document_type,
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Direct manager',
            'approver_type' => 'direct_manager', 'approver_ref' => '',
            'min_approvals' => 1, 'is_active' => true,
        ]);
        DocumentFormWorkflowPolicy::query()->create([
            'form_id' => $form->id,
            'position_id' => null,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        return $workflow;
    }

    private function makeOnBehalfDraft(DocumentForm $form, User $owner, User $creator, array $payload = ['title' => 'x']): DocumentFormSubmission
    {
        return DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_editor_user_ids' => [$creator->id],
            'payload' => $payload,
            'status' => 'draft',
        ]);
    }
}
