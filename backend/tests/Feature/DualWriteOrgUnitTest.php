<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DepartmentWorkflowBinding;
use App\Models\DocumentFormSubmission;
use App\Models\OrgUnit;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Org-model consolidation Phase 1 — dual-write. ทุกจุดที่เขียน department_id ต้องเขียน
 * org_unit_id คู่กันโดยให้ค่าทั้งสองมาจาก entity เดียวกัน (ไม่ขัดกัน). readers ยังใช้
 * department_id; เทสต์นี้ล็อกว่า org_unit_id ถูก populate พร้อมกัน. ดู doc/org-model-consolidation-spec.md
 */
class DualWriteOrgUnitTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_draft_create_dual_writes_owner_org_unit(): void
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

    public function test_start_dual_writes_org_unit_on_instance(): void
    {
        $orgUnit = OrgUnit::create(['name' => 'Ops', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('dw-req-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $orgUnit->id]);

        $workflow = $this->makeRoleWorkflow('dw_type_'.uniqid());

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $workflow->document_type,
            departmentId: null,
            requesterUserId: $requester->id,
            payload: ['x' => 1],
        );

        // departmentId omitted → start() resolves both dept + org_unit from requester.
        $this->assertSame($orgUnit->id, (int) $instance->fresh()->org_unit_id);
    }

    public function test_explicit_org_unit_arg_wins_over_requester(): void
    {
        $home = OrgUnit::create(['name' => 'Home', 'type' => 'department', 'is_active' => true]);
        $target = OrgUnit::create(['name' => 'Target', 'type' => 'department', 'is_active' => true]);
        $requester = $this->makeRegularUser('dw-exp-'.uniqid().'@example.test');
        $requester->update(['org_unit_id' => $home->id]);

        $workflow = $this->makeRoleWorkflow('dw_type2_'.uniqid());

        // CMMS-style: caller supplies the document's target org unit explicitly.
        $instance = app(ApprovalFlowService::class)->start(
            documentType: $workflow->document_type,
            departmentId: null,
            requesterUserId: $requester->id,
            payload: [],
            orgUnitId: $target->id,
        );

        $this->assertSame($target->id, (int) $instance->fresh()->org_unit_id);
    }

    public function test_bridge_resolves_department_to_org_unit(): void
    {
        $orgUnit = OrgUnit::create(['name' => 'Bridged', 'type' => 'department', 'is_active' => true]);
        $dept = Department::create(['name' => 'Legacy Dept', 'code' => 'LGY', 'is_active' => true, 'org_unit_id' => $orgUnit->id]);

        $this->assertSame($orgUnit->id, OrgUnit::idForDepartment($dept->id));
        $this->assertNull(OrgUnit::idForDepartment(null));
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

    private function makeRoleWorkflow(string $documentType): ApprovalWorkflow
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
        // start() ไม่มี formKey → resolve ผ่าน department_workflow_bindings (binding แรกของ document_type)
        $dept = Department::create(['name' => 'WF Dept '.uniqid(), 'code' => 'WF'.random_int(1000, 9999), 'is_active' => true]);
        DepartmentWorkflowBinding::query()->create([
            'department_id' => $dept->id,
            'document_type' => $documentType,
            'workflow_id' => $workflow->id,
        ]);

        return $workflow;
    }
}
