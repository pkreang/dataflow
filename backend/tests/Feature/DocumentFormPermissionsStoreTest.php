<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentType;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFormPermissionsStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_editable_by_steps_and_org_units_are_persisted(): void
    {
        $this->seedBase();
        $this->makeWorkflowWithSteps('maintenance_request', 3);
        $org1 = OrgUnit::create(['name' => 'Production', 'type' => 'department', 'is_active' => true]);
        $org2 = OrgUnit::create(['name' => 'IT', 'type' => 'department', 'is_active' => true]);

        $response = $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), [
            'form_key' => 'perm_form',
            'name' => 'Permissions Form',
            'document_type' => 'maintenance_request',
            'layout_columns' => 1,
            'table_name' => 'perm_form',
            'fields' => [
                [
                    'field_key' => 'remarks',
                    'label' => 'Remarks',
                    'field_type' => 'text',
                    'editable_by' => json_encode(['requester', 'step_2']),
                    'visible_to_org_units' => json_encode([$org1->id, $org2->id]),
                ],
            ],
        ]);

        $response->assertRedirect(route('settings.document-forms.index'));

        $form = DocumentForm::where('form_key', 'perm_form')->with('fields')->firstOrFail();
        $field = $form->fields->first();

        $this->assertEqualsCanonicalizing(['requester', 'step_2'], $field->editable_by);
        $this->assertEqualsCanonicalizing([$org1->id, $org2->id], $field->visible_to_org_units);
    }

    public function test_org_unit_visibility_persists_at_form_and_field(): void
    {
        $this->seedBase();
        $this->makeWorkflowWithSteps('maintenance_request', 3);
        $org1 = OrgUnit::create(['name' => 'Eng', 'type' => 'department', 'is_active' => true]);
        $org2 = OrgUnit::create(['name' => 'Ops', 'type' => 'department', 'is_active' => true]);

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), [
            'form_key' => 'org_form',
            'name' => 'Org Form',
            'document_type' => 'maintenance_request',
            'layout_columns' => 1,
            'table_name' => 'org_form',
            'allowed_org_units' => [$org1->id],
            'fields' => [
                [
                    'field_key' => 'remarks',
                    'label' => 'Remarks',
                    'field_type' => 'text',
                    'visible_to_org_units' => json_encode([$org1->id, $org2->id]),
                ],
            ],
        ])->assertRedirect(route('settings.document-forms.index'));

        $form = DocumentForm::where('form_key', 'org_form')->with(['fields', 'orgUnits'])->firstOrFail();
        $this->assertEqualsCanonicalizing([$org1->id], $form->orgUnits->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$org1->id, $org2->id], $form->fields->first()->visible_to_org_units);
    }

    public function test_default_requester_only_stores_as_null(): void
    {
        $this->seedBase();
        $this->makeWorkflowWithSteps('maintenance_request', 2);

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), [
            'form_key' => 'perm_null',
            'name' => 'Default Permissions',
            'document_type' => 'maintenance_request',
            'layout_columns' => 1,
            'table_name' => 'perm_null',
            'fields' => [
                [
                    'field_key' => 'remarks',
                    'label' => 'Remarks',
                    'field_type' => 'text',
                    'editable_by' => json_encode(['requester']),
                    'visible_to_org_units' => json_encode([]),
                ],
            ],
        ]);

        $field = DocumentForm::where('form_key', 'perm_null')->firstOrFail()->fields->first();

        $this->assertNull($field->editable_by, 'default ["requester"] should be stored as NULL to avoid column bloat');
        $this->assertNull($field->visible_to_org_units);
    }

    public function test_stale_steps_and_unknown_org_units_are_filtered_out(): void
    {
        $this->seedBase();
        $this->makeWorkflowWithSteps('maintenance_request', 2);
        $org = OrgUnit::create(['name' => 'Production', 'type' => 'department', 'is_active' => true]);

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), [
            'form_key' => 'perm_filter',
            'name' => 'Filter Permissions',
            'document_type' => 'maintenance_request',
            'layout_columns' => 1,
            'table_name' => 'perm_filter',
            'fields' => [
                [
                    'field_key' => 'remarks',
                    'label' => 'Remarks',
                    'field_type' => 'text',
                    // workflow has only step_1, step_2 — step_5 is stale and should be dropped
                    'editable_by' => json_encode(['requester', 'step_2', 'step_5']),
                    // 99999 is not a real org_unit
                    'visible_to_org_units' => json_encode([$org->id, 99999]),
                ],
            ],
        ]);

        $field = DocumentForm::where('form_key', 'perm_filter')->firstOrFail()->fields->first();

        $this->assertEqualsCanonicalizing(['requester', 'step_2'], $field->editable_by);
        $this->assertSame([$org->id], $field->visible_to_org_units);
    }

    private function seedBase(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        DocumentType::updateOrCreate(
            ['code' => 'maintenance_request'],
            ['label_en' => 'Maintenance', 'label_th' => 'แจ้งซ่อม', 'is_active' => true]
        );
    }

    private function makeWorkflowWithSteps(string $documentType, int $stepCount): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::create([
            'document_type' => $documentType,
            'name' => 'Default flow',
            'is_active' => true,
        ]);
        for ($i = 1; $i <= $stepCount; $i++) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $workflow->id,
                'step_no' => $i,
                'name' => "Step {$i}",
                'approver_type' => 'position',
                'approver_ref' => '0',
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }

        return $workflow;
    }

    private function actingAsSuperAdmin(): self
    {
        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'is_super_admin' => true,
                'org_unit_id' => null,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => [],
        ]);
    }
}
