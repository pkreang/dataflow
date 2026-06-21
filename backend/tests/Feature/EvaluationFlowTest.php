<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Database\Seeders\EvaluationFormSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class EvaluationFlowTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            EvaluationFormSeeder::class,
        ]);
    }

    private function makeApprovedSubmissionForOwner(User $owner, string $instanceStatus = 'approved'): DocumentFormSubmission
    {
        $workflow = \App\Models\ApprovalWorkflow::create([
            'name' => 'Test Workflow',
            'document_type' => 'test',
            'is_active' => true,
        ]);

        $form = DocumentForm::create([
            'form_key' => 'test_for_eval',
            'name' => 'Test Form For Eval',
            'document_type' => 'test',
            'is_active' => true,
            'layout_columns' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 1,
        ]);

        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $owner->id,
            'document_type' => 'test',
            'reference_no' => 'TEST-0001',
            'payload' => [],
            'current_step_no' => 1,
            'status' => $instanceStatus,
        ]);

        return DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'approval_instance_id' => $instance->id,
            'payload' => ['title' => 'demo'],
            'status' => 'submitted',
        ]);
    }

    public function test_owner_can_submit_evaluation_for_approved_work(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => [
                    'overall_rating' => '5',
                    'comment' => 'Great work',
                ],
            ]);

        $response->assertRedirect(route('forms.submission.show', $submission));

        $this->assertDatabaseHas('document_form_submissions', [
            'parent_submission_id' => $submission->id,
            'user_id' => $owner->id,
            'status' => 'submitted',
        ]);

        $eval = DocumentFormSubmission::where('parent_submission_id', $submission->id)->first();
        $this->assertSame('5', $eval->payload['overall_rating']);
        $this->assertSame('Great work', $eval->payload['comment']);
    }

    public function test_non_owner_cannot_submit_evaluation(): void
    {
        $owner = $this->makeRegularUser('owner@example.test');
        $intruder = $this->makeRegularUser('intruder@example.test');
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        $this->actingAsWebSession($intruder)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('document_form_submissions', [
            'parent_submission_id' => $submission->id,
        ]);
    }

    public function test_duplicate_evaluation_blocked(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        // First eval — ok
        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '4'],
            ])
            ->assertRedirect();

        // Second eval — blocked
        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertStatus(422);

        $this->assertSame(1, DocumentFormSubmission::where('parent_submission_id', $submission->id)->count());
    }

    public function test_desktop_evaluation_submit_redirects_to_desktop_show(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertRedirect(route('forms.submission.show', $submission));
    }

    public function test_mobile_evaluation_submit_redirects_to_mobile_detail(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        $this->actingAsWebSession($owner)
            ->post("/m/requests/{$submission->id}/evaluate", [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertRedirect(route('mobile.request.detail', $submission));

        $this->assertDatabaseHas('document_form_submissions', [
            'parent_submission_id' => $submission->id,
        ]);
    }

    public function test_evaluate_redirects_gracefully_when_no_active_eval_form(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        DocumentForm::where('document_type', 'evaluation')->update(['is_active' => false]);

        $this->actingAsWebSession($owner)
            ->get(route('forms.submission.evaluate', $submission))
            ->assertRedirect(route('forms.submission.show', $submission));
    }

    public function test_evaluate_cta_hidden_when_no_active_eval_form(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);
        $submission->form->update(['evaluation_enabled' => true]);
        DocumentForm::where('document_type', 'evaluation')->update(['is_active' => false]);

        $this->actingAsWebSession($owner)
            ->get(route('forms.submission.show', $submission))
            ->assertOk()
            ->assertDontSee(route('forms.submission.evaluate', $submission), false);
    }

    public function test_evaluate_cta_shown_when_eval_form_available(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);
        $submission->form->update(['evaluation_enabled' => true]);

        $this->actingAsWebSession($owner)
            ->get(route('forms.submission.show', $submission))
            ->assertOk()
            ->assertSee(route('forms.submission.evaluate', $submission), false);
    }

    public function test_create_form_renders_for_owner_of_approved_work(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner);

        $this->actingAsWebSession($owner)
            ->get(route('forms.submission.evaluate', $submission))
            ->assertOk()
            ->assertSee('ความพึงพอใจโดยรวม'); // overall_rating label from EvaluationFormSeeder
    }

    public function test_evaluate_blocked_when_parent_not_approved(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner, 'pending');

        $this->actingAsWebSession($owner)
            ->get(route('forms.submission.evaluate', $submission))
            ->assertStatus(422);

        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertStatus(422);
    }

    public function test_target_specific_eval_form_used_when_document_type_matches(): void
    {
        $owner = $this->makeRegularUser();
        $submission = $this->makeApprovedSubmissionForOwner($owner); // parent document_type = 'test'

        $specific = DocumentForm::create([
            'form_key' => 'evaluation_for_test',
            'name' => 'Test-specific Evaluation',
            'document_type' => 'evaluation',
            'is_active' => true,
            'target_document_types' => ['test'],
            'layout_columns' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $specific->id, 'field_key' => 'overall_rating', 'label' => 'Rating',
            'field_type' => 'radio', 'is_required' => false, 'sort_order' => 1,
            'options' => ['5', '4', '3', '2', '1'],
        ]);

        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.evaluate.store', $submission), [
                'fields' => ['overall_rating' => '5'],
            ])
            ->assertRedirect();

        $eval = DocumentFormSubmission::where('parent_submission_id', $submission->id)->firstOrFail();
        $this->assertSame($specific->id, $eval->form_id);
    }
}
