<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\OrgUnit;
use App\Models\OrgUnitWorkflowBinding;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Org-model consolidation — every per-submission / per-instance write captures the
 * owner's org_unit_id. (Formerly Phase 1 dual-write of department_id; department is
 * gone, so this now just locks the org_unit write path.)
 */
class DualWriteOrgUnitTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_draft_create_writes_owner_org_unit(): void
    {
        $orgUnit = OrgUnit::create(['name' => 'Engineering', 'type' => 'department', 'is_active' => true]);
        $user = $this->makeRegularUser('dw-owner-'.uniqid().'@example.test');
        $user->update(['org_unit_id' => $orgUnit->id]);

        $form = $this->makeSimpleForm();

        $this->actingAsWebSession($user->fresh())
            ->post(route('forms.draft.store', $form), ['fields' => ['title' => 'x']])
            ->assertRedirect();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame($orgUnit->id, (int) $submission->org_unit_id);
    }

    public function test_start_writes_org_unit_on_instance(): void
    {
        $orgUnit = OrgUnit::create(['name' => 'Ops', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('dw-req-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $orgUnit->id]);

        $workflow = $this->makeRoleWorkflow('dw_type_'.uniqid(), $orgUnit->id);

        // org_unit omitted → start() resolves it from the requester.
        $instance = app(ApprovalFlowService::class)->start(
            documentType: $workflow->document_type,
            requesterUserId: $requester->id,
            payload: ['x' => 1],
        );

        $this->assertSame($orgUnit->id, (int) $instance->fresh()->org_unit_id);
    }

    public function test_explicit_org_unit_arg_wins_over_requester(): void
    {
        $home = OrgUnit::create(['name' => 'Home', 'type' => 'department', 'is_active' => true]);
        $target = OrgUnit::create(['name' => 'Target', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('dw-exp-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $home->id]);

        $workflow = $this->makeRoleWorkflow('dw_type2_'.uniqid(), $target->id);

        // CMMS-style: caller supplies the document's target org unit explicitly.
        $instance = app(ApprovalFlowService::class)->start(
            documentType: $workflow->document_type,
            requesterUserId: $requester->id,
            payload: [],
            orgUnitId: $target->id,
        );

        $this->assertSame($target->id, (int) $instance->fresh()->org_unit_id);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function makeSimpleForm(): DocumentForm
    {
        $form = DocumentForm::factory()->create([
            'document_type' => 'dw_form_'.uniqid(),
            'is_active' => true,
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);

        return $form->fresh('fields');
    }

    private function makeRoleWorkflow(string $documentType, int $orgUnitId): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::query()->create([
            'name' => 'DW WF '.uniqid(),
            'document_type' => $documentType,
            'is_active' => true,
        ]);
        $approver = $this->makeRegularUser('dw-appr-'.uniqid().'@example.test');
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Approver',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        // start() has no formKey → resolve via org_unit_workflow_bindings on the
        // requester's org unit.
        OrgUnitWorkflowBinding::query()->create([
            'org_unit_id' => $orgUnitId,
            'document_type' => $documentType,
            'workflow_id' => $workflow->id,
        ]);

        return $workflow;
    }
}
