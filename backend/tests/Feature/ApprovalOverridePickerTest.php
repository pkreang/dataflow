<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Requester-override picker visibility. Regression: "ส่ง" from the create page
 * lands on edit-draft with an auto-submit script — which fired before the user
 * could ever see the approver picker. With override stages present the
 * auto-submit must pause.
 */
class ApprovalOverridePickerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_autosubmit_paused_when_override_picker_present(): void
    {
        [$submission, $user] = $this->makeDraft(override: true);

        $response = $this->actingAsWebSession($user)
            ->session(['autosubmit' => true])
            ->get(route('forms.draft.edit', $submission))
            ->assertOk();

        $response->assertSee('picked_approvers[1]', false);
        $response->assertDontSee('submitForm.submit()', false);
        $response->assertSee(__('common.pick_approver_before_submit'));
    }

    public function test_create_page_shows_picker_upfront(): void
    {
        [$submission, $user] = $this->makeDraft(override: true);

        $this->actingAsWebSession($user)
            ->get(route('forms.create', $submission->form))
            ->assertOk()
            ->assertSee('picked_approvers[1]', false);
    }

    public function test_pick_made_on_create_carries_through_and_autosubmits(): void
    {
        [$submission, $user, $approver] = $this->makeDraft(override: true);
        $form = $submission->form;

        // Filing from the create page WITH the picker answered (use-default '')
        $response = $this->actingAsWebSession($user)
            ->post(route('forms.draft.store', $form), [
                'fields' => ['title' => 'picked upfront'],
                'picked_approvers' => [1 => (string) $approver->id],
                '_intent' => 'submit',
            ])
            ->assertRedirect();

        $follow = $this->get($response->headers->get('Location'));
        $follow->assertOk();
        // Choice already made → auto-submit proceeds (no pause banner)...
        $follow->assertSee('submitForm.submit()', false);
        $follow->assertDontSee(__('common.pick_approver_before_submit'));
        // ...with the picked approver preselected in the carried-over form.
        $follow->assertSee('value="'.$approver->id.'" selected', false);
    }

    public function test_autosubmit_still_fires_without_override(): void
    {
        [$submission, $user] = $this->makeDraft(override: false);

        $response = $this->actingAsWebSession($user)
            ->session(['autosubmit' => true])
            ->get(route('forms.draft.edit', $submission))
            ->assertOk();

        $response->assertDontSee('picked_approvers[1]', false);
        $response->assertSee('submitForm.submit()', false);
    }

    /** @return array{0: DocumentFormSubmission, 1: \App\Models\User, 2: \App\Models\User} */
    private function makeDraft(bool $override): array
    {
        Setting::set('approval.allow_requester_override', true);
        \Spatie\Permission\Models\Permission::updateOrCreate(
            ['name' => 'approval.approve', 'guard_name' => 'web'],
            ['module' => 'approval', 'action' => 'approve']
        );

        $user = $this->makeRegularUser('ovp-'.uniqid().'@example.test');
        $approver = $this->makeRegularUser('ovp-appr-'.uniqid().'@example.test');
        $approver->givePermissionTo('approval.approve');

        $form = DocumentForm::factory()->create([
            'form_key' => 'ovp_'.uniqid(),
            'document_type' => 'ovp_test_'.uniqid(),
            'is_active' => true,
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'OVP WF '.uniqid(),
            'document_type' => $form->document_type,
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Step 1',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'allow_requester_override' => $override,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        DocumentFormWorkflowPolicy::query()->create([
            'form_id' => $form->id,
            'department_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);

        return [$submission, $user, $approver];
    }
}
