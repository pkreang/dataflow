<?php

namespace Tests\Feature\Settings;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\Position;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class ApprovalRoutingPolicyTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeForm(string $docType = 'repair_request'): DocumentForm
    {
        return DocumentForm::factory()->create(['document_type' => $docType]);
    }

    private function makeWorkflow(string $docType = 'repair_request', ?string $name = null): ApprovalWorkflow
    {
        return ApprovalWorkflow::create([
            'name' => $name ?? 'WF-'.uniqid(),
            'document_type' => $docType,
            'is_active' => true,
        ]);
    }

    private function makeWorkflowWithStage(string $docType, User $approver): ApprovalWorkflow
    {
        $wf = $this->makeWorkflow($docType);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id,
            'step_no' => 1,
            'name' => 'Approve',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        return $wf;
    }

    public function test_routing_page_renders_with_form_cards(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        $this->actingAsWebSession($admin)
            ->get(route('settings.approval-routing'))
            ->assertOk()
            ->assertSee($form->name)
            ->assertSee($workflow->name);
    }

    public function test_set_default_creates_global_policy_row(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $workflow->id],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'department_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
        ]);
    }

    public function test_update_default_modifies_existing_row_without_duplicating(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $wf1 = $this->makeWorkflow();
        $wf2 = $this->makeWorkflow();
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'workflow_id' => $wf1->id,
            'use_amount_condition' => false,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $wf2->id],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertSame(1, DocumentFormWorkflowPolicy::where('form_id', $form->id)->count());
        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'workflow_id' => $wf2->id,
        ]);
    }

    public function test_clear_default_does_not_delete_advanced_policy(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $policy = DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'workflow_id' => null,
            'use_amount_condition' => true,
            'amount_field_key' => 'total',
        ]);
        $policy->ranges()->create([
            'min_amount' => 0,
            'max_amount' => null,
            'workflow_id' => $workflow->id,
            'sort_order' => 1,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => ''],
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('document_form_workflow_policies', ['id' => $policy->id]);
    }

    public function test_add_department_exception(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $dept = Department::factory()->create();

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'exceptions' => [
                ['form_id' => $form->id, 'scope' => 'department', 'department_id' => $dept->id, 'workflow_id' => $workflow->id],
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'department_id' => $dept->id,
            'position_id' => null,
            'workflow_id' => $workflow->id,
        ]);
    }

    public function test_add_position_exception(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $position = Position::create(['name' => 'Test Position', 'code' => 'TEST_POS', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'exceptions' => [
                ['form_id' => $form->id, 'scope' => 'position', 'position_id' => $position->id, 'workflow_id' => $workflow->id],
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'department_id' => null,
            'position_id' => $position->id,
            'workflow_id' => $workflow->id,
        ]);
    }

    public function test_duplicate_exception_updates_instead_of_duplicating(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $wf1 = $this->makeWorkflow();
        $wf2 = $this->makeWorkflow();
        $dept = Department::factory()->create();
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'department_id' => $dept->id,
            'workflow_id' => $wf1->id,
            'use_amount_condition' => false,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'exceptions' => [
                ['form_id' => $form->id, 'scope' => 'department', 'department_id' => $dept->id, 'workflow_id' => $wf2->id],
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertSame(1, DocumentFormWorkflowPolicy::where('form_id', $form->id)->where('department_id', $dept->id)->count());
        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'department_id' => $dept->id,
            'workflow_id' => $wf2->id,
        ]);
    }

    public function test_delete_simple_exception(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $dept = Department::factory()->create();
        $policy = DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'department_id' => $dept->id,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'deleted_policy_ids' => [$policy->id],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseMissing('document_form_workflow_policies', ['id' => $policy->id]);
    }

    public function test_delete_advanced_policy_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $dept = Department::factory()->create();
        $policy = DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'department_id' => $dept->id,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
            'field_conditions' => [
                ['field_key' => 'x', 'operator' => '=', 'value' => 'y', 'workflow_id' => $workflow->id, 'priority' => 1],
            ],
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'deleted_policy_ids' => [$policy->id],
        ])->assertSessionHasErrors();

        $this->assertDatabaseHas('document_form_workflow_policies', ['id' => $policy->id]);
    }

    public function test_workflow_of_wrong_document_type_is_not_saved(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm('repair_request');
        $workflow = $this->makeWorkflow('leave_request');

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $workflow->id],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseMissing('document_form_workflow_policies', [
            'form_id' => $form->id,
            'workflow_id' => $workflow->id,
        ]);
    }

    public function test_department_workflow_bindings_are_untouched(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = $this->makeForm();
        $workflow = $this->makeWorkflow();
        $dept = Department::factory()->create();
        DepartmentWorkflowBinding::create([
            'department_id' => $dept->id,
            'document_type' => 'repair_request',
            'workflow_id' => $workflow->id,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $workflow->id],
            'exceptions' => [
                ['form_id' => $form->id, 'scope' => 'department', 'department_id' => $dept->id, 'workflow_id' => $workflow->id],
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseHas('department_workflow_bindings', [
            'department_id' => $dept->id,
            'document_type' => 'repair_request',
            'workflow_id' => $workflow->id,
        ]);
    }

    public function test_override_toggle_persists(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'allow_requester_override' => '1',
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertTrue(\App\Models\Setting::getBool('approval.allow_requester_override'));
    }

    public function test_saved_default_routes_submission_through_flow_service(): void
    {
        $admin = $this->makeSuperAdmin();
        $docType = 'attr_routing_'.uniqid();
        $approver = User::create([
            'first_name' => 'App', 'last_name' => 'Rover',
            'email' => 'approver-routing@example.com', 'password' => 'password',
            'is_active' => true, 'is_super_admin' => false,
        ]);
        $requester = User::create([
            'first_name' => 'Req', 'last_name' => 'Uester',
            'email' => 'requester-routing@example.com', 'password' => 'password',
            'is_active' => true, 'is_super_admin' => false,
        ]);
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $form = DocumentForm::factory()->create(['document_type' => $docType]);
        $wfDefault = $this->makeWorkflowWithStage($docType, $approver);
        $wfException = $this->makeWorkflowWithStage($docType, $approver);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $wfDefault->id],
            'exceptions' => [
                ['form_id' => $form->id, 'scope' => 'department', 'department_id' => $deptA->id, 'workflow_id' => $wfException->id],
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $svc = app(ApprovalFlowService::class);

        $instanceA = $svc->start(
            documentType: $docType,
            departmentId: $deptA->id,
            requesterUserId: $requester->id,
            formKey: $form->form_key,
        );
        $this->assertSame($wfException->id, $instanceA->workflow_id);

        $instanceB = $svc->start(
            documentType: $docType,
            departmentId: $deptB->id,
            requesterUserId: $requester->id,
            formKey: $form->form_key,
        );
        $this->assertSame($wfDefault->id, $instanceB->workflow_id);
    }
}
