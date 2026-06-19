<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\OrgUnit;
use App\Models\OrgUnitWorkflowBinding;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Org-model consolidation — workflow resolution via org_unit config.
 * resolveWorkflowId/binding อ่าน org_unit config (department ถูกลบแล้ว).
 * priority: position > org_unit > global. ดู doc/org-model-consolidation-spec.md
 */
class OrgUnitWorkflowResolutionTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_org_unit_binding_resolves_workflow_without_form(): void
    {
        $org = OrgUnit::create(['name' => 'Plant A', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('owr-bind-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $org->id]);

        $wf = $this->makeWorkflow('owr_bind_'.uniqid());
        OrgUnitWorkflowBinding::create([
            'org_unit_id' => $org->id, 'document_type' => $wf->document_type, 'workflow_id' => $wf->id,
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $wf->document_type,
            requesterUserId: $requester->id,
        );

        $this->assertSame($wf->id, (int) $instance->workflow_id);
    }

    public function test_org_unit_policy_resolves_workflow_with_form(): void
    {
        $org = OrgUnit::create(['name' => 'Plant C', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('owr-pol-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $org->id]);

        $form = $this->makeForm();
        $wfOrg = $this->makeWorkflow($form->document_type);
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id, 'org_unit_id' => $org->id, 'position_id' => null,
            'workflow_id' => $wfOrg->id, 'use_amount_condition' => false,
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $form->document_type,
            requesterUserId: $requester->id,
            formKey: $form->form_key,
            orgUnitId: $org->id,
        );

        $this->assertSame($wfOrg->id, (int) $instance->workflow_id);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function makeWorkflow(string $documentType): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::create([
            'name' => 'OWR WF '.uniqid(),
            'document_type' => $documentType,
            'is_active' => true,
        ]);
        $approver = $this->makeRegularUser('owr-appr-'.uniqid().'@example.test');
        ApprovalWorkflowStage::create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Approver',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);

        return $workflow;
    }

    private function makeForm(): DocumentForm
    {
        $form = DocumentForm::factory()->create([
            'document_type' => 'owr_form_'.uniqid(),
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);

        return $form->fresh('fields');
    }
}
