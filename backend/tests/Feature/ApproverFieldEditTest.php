<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use App\Services\FormSchemaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Approver inline field-edit on dynamic-form submissions.
 *
 * The current pending approver may edit fields tagged editable_by=['step_N'] (or
 * 'user:{id}') on the submission view; the PATCH approvals.update-fields route
 * writes back to document_form_submissions.payload (+ fdata_* when present) and
 * re-filters non-editable fields server-side. Owners / non-current approvers see
 * a read-only view.
 */
class ApproverFieldEditTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    private static int $seq = 0;

    public function test_current_approver_sees_edit_form_with_editable_field(): void
    {
        [$submission, $instance, $approver] = $this->makeScenario();

        $response = $this->actingAsWebSession($approver)
            ->get(route('forms.submission.show', $submission));

        $response->assertOk();
        // Edit form is rendered → the editable field uses the field_updates[] name
        // and the save button + PATCH action are present.
        $response->assertSee('field_updates[approver_note]', false);
        $response->assertSee(route('approvals.update-fields', $instance), false);
        $response->assertSee(__('common.save_fields'));
        // Approve/reject must go through an explicit confirm dialog.
        // (dialog titles are embedded via @js() so Thai text is \u-escaped —
        // assert the structural marker instead of the literal string)
        $response->assertSee('data-approval-confirm-dialog', false);
        $response->assertSee('confirmProceed()', false);
    }

    public function test_owner_sees_readonly_view_without_edit_form(): void
    {
        [$submission, $instance, , $requester] = $this->makeScenario();

        $response = $this->actingAsWebSession($requester)
            ->get(route('forms.submission.show', $submission));

        $response->assertOk();
        $response->assertDontSee('field_updates[', false);
        $response->assertDontSee(route('approvals.update-fields', $instance), false);
    }

    public function test_approver_update_persists_editable_field_and_drops_non_editable(): void
    {
        [$submission, $instance, $approver] = $this->makeScenario();

        $this->actingAsWebSession($approver)
            ->patch(route('approvals.update-fields', $instance), [
                'field_updates' => [
                    'approver_note' => 'APPROVER WROTE THIS',
                    'title' => 'HACK ATTEMPT', // not editable by step_1 → must be dropped
                ],
            ])
            ->assertRedirect();

        $fresh = $submission->fresh();
        $this->assertSame('APPROVER WROTE THIS', $fresh->payload['approver_note']);
        // Server re-filter: a field without the approver's step token is untouched.
        $this->assertSame('original title', $fresh->payload['title']);

        $this->assertDatabaseHas('submission_activity_log', [
            'submission_id' => $submission->id,
            'user_id' => $approver->id,
            'action' => 'updated',
        ]);
    }

    public function test_non_current_approver_cannot_update_fields(): void
    {
        [, $instance, , , $stranger] = $this->makeScenario();

        // A user who holds approval.approve but is NOT the step-1 approver.
        $this->actingAsWebSession($stranger)
            ->patch(route('approvals.update-fields', $instance), [
                'field_updates' => ['approver_note' => 'SHOULD NOT SAVE'],
            ])
            ->assertForbidden();
    }

    public function test_current_approver_without_any_editable_field_sees_no_form(): void
    {
        [$submission, $instance, $approver] = $this->makeScenario();

        // Strip the only step_1-editable token → approver is current, but has
        // nothing to edit. The edit form must not render (no empty save button).
        DocumentFormField::where('form_id', $submission->form_id)
            ->where('field_key', 'approver_note')
            ->update(['editable_by' => ['requester']]);

        $response = $this->actingAsWebSession($approver)
            ->get(route('forms.submission.show', $submission));

        $response->assertOk();
        // The PATCH edit form must not be present (approval-action JS also contains the
        // input name pattern, so check the route URL and save-button label instead).
        $response->assertDontSee(route('approvals.update-fields', $instance), false);
        $response->assertDontSee(__('common.save_fields'));
    }

    public function test_approver_update_syncs_dedicated_fdata_table(): void
    {
        [$submission, $instance, $approver] = $this->makeScenario(dedicated: true);

        $this->actingAsWebSession($approver)
            ->patch(route('approvals.update-fields', $instance), [
                'field_updates' => ['approver_note' => 'SYNCED TO FDATA'],
            ])
            ->assertRedirect();

        $fresh = $submission->fresh();
        $this->assertSame('SYNCED TO FDATA', $fresh->payload['approver_note']);
        // Dual-write reached the dedicated fdata_* row, not just the JSON payload.
        $this->assertNotNull($fresh->fdata_row_id);
        $this->assertSame('SYNCED TO FDATA', DB::table('fdata_approver_edit')
            ->where('id', $fresh->fdata_row_id)
            ->value('approver_note'));
    }

    // ---------- helpers ----------

    private function makeUser(string $tag): User
    {
        self::$seq++;

        return User::query()->create([
            'first_name' => 'FE',
            'last_name' => $tag.self::$seq,
            'email' => "fe_{$tag}_".self::$seq.'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    /**
     * Dynamic form with one approver-editable field (step_1) + one requester-only
     * field, a submitted submission, and an instance pending at step 1.
     *
     * @return array{0: DocumentFormSubmission, 1: ApprovalInstance, 2: User, 3: User, 4: User}
     */
    private function makeScenario(bool $dedicated = false): array
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $requester = $this->makeUser('req');
        $approver = $this->makeUser('appr');
        $stranger = $this->makeUser('stranger');
        $approver->givePermissionTo('approval.approve');
        $stranger->givePermissionTo('approval.approve');

        $form = DocumentForm::factory()->create([
            'document_type' => 'fieldedit_test',
            'submission_table' => $dedicated ? 'fdata_approver_edit' : null,
        ]);

        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
            'is_searchable' => $dedicated,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'approver_note', 'label' => 'Approver note',
            'field_type' => 'text', 'sort_order' => 2, 'editable_by' => ['step_1'],
            'is_searchable' => $dedicated,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'FE WF '.self::$seq,
            'document_type' => 'fieldedit_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'fieldedit_test',
            'reference_no' => 'FE-'.self::$seq,
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
            'payload' => ['title' => 'original title', 'approver_note' => ''],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        if ($dedicated) {
            $schema = app(FormSchemaService::class);
            $schema->createTable($form->load('fields'));
            $rowId = $schema->insertRow($form, $submission->payload, [
                'user_id' => $requester->id,
                'status' => 'submitted',
            ]);
            $submission->update(['fdata_row_id' => $rowId]);
        }

        return [$submission, $instance, $approver, $requester, $stranger];
    }
}
