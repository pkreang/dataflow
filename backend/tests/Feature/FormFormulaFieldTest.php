<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * End-to-end coverage for the `formula` field type — server-side recompute
 * during draft save. The hidden mirror input is editable in devtools, so the
 * test deliberately sends a wrong value and asserts the persisted payload
 * carries the correctly computed result.
 */
class FormFormulaFieldTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_storing_draft_recomputes_formula_value_server_side(): void
    {
        [$form, $requester] = $this->makeFormWithFormula();

        $response = $this->actingAsWebSession($requester)
            ->post(route('forms.draft.store', $form), [
                'fields' => [
                    'score_a' => 5,
                    'score_b' => 3,
                    'score_c' => 2,
                    // Lie about the total — server must overwrite it.
                    'total' => 999,
                ],
            ]);

        $response->assertRedirect();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $payload = $submission->payload;

        // Server recompute: 5 + 3 + 2 = 10 (not 999)
        $this->assertSame(10.0, (float) $payload['total']);
        $this->assertSame('5', (string) $payload['score_a']);
    }

    public function test_updating_draft_recomputes_formula_value_server_side(): void
    {
        [$form, $requester] = $this->makeFormWithFormula();

        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => ['score_a' => 1, 'score_b' => 1, 'score_c' => 1, 'total' => 3.0],
            'status' => 'draft',
        ]);

        $response = $this->actingAsWebSession($requester)
            ->put(route('forms.draft.update', $submission), [
                'fields' => [
                    'score_a' => 4,
                    'score_b' => 6,
                    'score_c' => 0,
                    'total' => 999, // intentionally wrong — server overwrites
                ],
            ]);

        $response->assertRedirect();

        $fresh = $submission->fresh();
        $this->assertSame(10.0, (float) $fresh->payload['total']);
    }

    public function test_empty_expression_persists_null(): void
    {
        $requester = $this->makeRegularUser('formula-empty@example.test');
        $form = DocumentForm::factory()->create();
        DocumentFormField::query()->create([
            'form_id' => $form->id,
            'field_key' => 'subtotal',
            'label' => 'Subtotal',
            'field_type' => 'formula',
            'is_required' => false,
            'sort_order' => 1,
            'options' => ['expression' => '', 'decimals' => 2],
            'editable_by' => ['requester'],
        ]);

        $response = $this->actingAsWebSession($requester)
            ->post(route('forms.draft.store', $form), [
                // Hidden mirror sends empty string when formula value is null;
                // server should overwrite it with literal null on empty expression.
                'fields' => ['subtotal' => ''],
            ]);

        $response->assertRedirect();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertNull($submission->payload['subtotal']);
    }

    public function test_show_submission_renders_stored_formula_value(): void
    {
        [$form, $requester] = $this->makeFormWithFormula();

        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => ['score_a' => 5, 'score_b' => 3, 'score_c' => 2, 'total' => 10.0],
            'status' => 'submitted',
        ]);

        // The detail page has no Alpine form scope (`fp`), so the formula input
        // must carry the DB value as a static attribute, formatted per decimals.
        $this->actingAsWebSession($requester)
            ->get(route('forms.submission.show', $submission))
            ->assertOk()
            ->assertSee('value="10.00"', false);
    }

    public function test_approver_field_update_recomputes_formula(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $requester = $this->makeRegularUser('formula-upd-req@example.test');
        $approver = $this->makeRegularUser('formula-upd-appr@example.test');
        $approver->givePermissionTo('approval.approve');

        $form = DocumentForm::factory()->create(['document_type' => 'formula_upd_test']);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'score_a', 'label' => 'A',
            'field_type' => 'number', 'sort_order' => 1, 'editable_by' => ['step_1'],
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'score_b', 'label' => 'B',
            'field_type' => 'number', 'sort_order' => 2, 'editable_by' => ['requester'],
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'total', 'label' => 'Total',
            'field_type' => 'formula', 'sort_order' => 3,
            'options' => ['expression' => 'score_a + score_b', 'decimals' => 2],
            'editable_by' => ['requester'],
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'Formula update WF',
            'document_type' => 'formula_upd_test',
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'formula_upd_test',
            'reference_no' => 'FUPD-1',
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
        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => ['score_a' => 1, 'score_b' => 2, 'total' => 3.0],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        $this->actingAsWebSession($approver)
            ->patch(route('approvals.update-fields', $instance), [
                'field_updates' => ['score_a' => 10],
            ])
            ->assertRedirect();

        // total must be recomputed from the merged payload: 10 + 2 = 12
        $this->assertSame(12.0, (float) $submission->fresh()->payload['total']);
    }

    /**
     * Build a form with three number fields (score_a/b/c) plus a formula
     * field `total = score_a + score_b + score_c`, plus a requester user.
     *
     * @return array{0: DocumentForm, 1: \App\Models\User}
     */
    private function makeFormWithFormula(): array
    {
        $requester = $this->makeRegularUser('formula-tester-'.uniqid().'@example.test');
        $form = DocumentForm::factory()->create();

        foreach (['score_a', 'score_b', 'score_c'] as $i => $key) {
            DocumentFormField::query()->create([
                'form_id' => $form->id,
                'field_key' => $key,
                'label' => strtoupper($key),
                'field_type' => 'number',
                'is_required' => false,
                'sort_order' => $i + 1,
                'options' => null,
                'editable_by' => ['requester'],
            ]);
        }

        DocumentFormField::query()->create([
            'form_id' => $form->id,
            'field_key' => 'total',
            'label' => 'Total',
            'field_type' => 'formula',
            'is_required' => false,
            'sort_order' => 4,
            'options' => ['expression' => 'score_a + score_b + score_c', 'decimals' => 2],
            'editable_by' => ['requester'],
        ]);

        return [$form->fresh('fields'), $requester];
    }
}
