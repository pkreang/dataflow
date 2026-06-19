<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
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
 * Org-model consolidation Phase 2a — workflow resolution reader switch.
 * resolveWorkflowId/binding อ่าน org_unit config ก่อน (ชนะ), department เป็น fallback.
 * priority: position > org_unit > department > global. ดู doc/org-model-consolidation-spec.md
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
            departmentId: null,
            requesterUserId: $requester->id,
        );

        $this->assertSame($wf->id, (int) $instance->workflow_id);
    }

    public function test_org_unit_binding_beats_department_binding(): void
    {
        $org = OrgUnit::create(['name' => 'Plant B', 'type' => 'department', 'is_active' => true]);
        $dept = Department::create(['name' => 'Dept B', 'code' => 'DB'.random_int(1000, 9999), 'is_active' => true]);
        $requester = $this->makeRegularUser('owr-both-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $org->id, 'department_id' => $dept->id]);

        $docType = 'owr_both_'.uniqid();
        $wfDept = $this->makeWorkflow($docType);
        $wfOrg = $this->makeWorkflow($docType);
        DepartmentWorkflowBinding::create(['department_id' => $dept->id, 'document_type' => $docType, 'workflow_id' => $wfDept->id]);
        OrgUnitWorkflowBinding::create(['org_unit_id' => $org->id, 'document_type' => $docType, 'workflow_id' => $wfOrg->id]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            departmentId: $dept->id,
            requesterUserId: $requester->id,
            orgUnitId: $org->id,
        );

        $this->assertSame($wfOrg->id, (int) $instance->workflow_id);
    }

    public function test_org_unit_policy_beats_department_policy(): void
    {
        $org = OrgUnit::create(['name' => 'Plant C', 'type' => 'department', 'is_active' => true]);
        $dept = Department::create(['name' => 'Dept C', 'code' => 'DC'.random_int(1000, 9999), 'is_active' => true]);
        $requester = $this->makeRegularUser('owr-pol-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $org->id, 'department_id' => $dept->id]);

        $form = $this->makeForm();
        $wfDept = $this->makeWorkflow($form->document_type);
        $wfOrg = $this->makeWorkflow($form->document_type);
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id, 'department_id' => $dept->id, 'position_id' => null,
            'workflow_id' => $wfDept->id, 'use_amount_condition' => false,
        ]);
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id, 'org_unit_id' => $org->id, 'position_id' => null,
            'workflow_id' => $wfOrg->id, 'use_amount_condition' => false,
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $form->document_type,
            departmentId: $dept->id,
            requesterUserId: $requester->id,
            formKey: $form->form_key,
            orgUnitId: $org->id,
        );

        $this->assertSame($wfOrg->id, (int) $instance->workflow_id);
    }

    public function test_department_fallback_when_no_org_config(): void
    {
        // org config ว่าง → ตก fallback ไป department (พฤติกรรมเดิม ไม่พัง)
        $dept = Department::create(['name' => 'Dept D', 'code' => 'DD'.random_int(1000, 9999), 'is_active' => true]);
        $requester = $this->makeRegularUser('owr-fb-'.uniqid().'@example.test');
        $requester->update(['department_id' => $dept->id]);

        $docType = 'owr_fb_'.uniqid();
        $wfDept = $this->makeWorkflow($docType);
        DepartmentWorkflowBinding::create(['department_id' => $dept->id, 'document_type' => $docType, 'workflow_id' => $wfDept->id]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            departmentId: $dept->id,
            requesterUserId: $requester->id,
        );

        $this->assertSame($wfDept->id, (int) $instance->workflow_id);
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
