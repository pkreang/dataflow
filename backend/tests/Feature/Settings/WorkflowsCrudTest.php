<?php

namespace Tests\Feature\Settings;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class WorkflowsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_workflow_with_role_stage(): void
    {
        $admin = $this->makeSuperAdmin();
        $role = Role::firstWhere('name', 'admin') ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->actingAsWebSession($admin)->post(route('settings.workflow.store'), [
            'name' => 'Standard approval',
            'document_type' => 'generic',
            'is_active' => 1,
            'stages' => [
                [
                    'step_no' => 1,
                    'name' => 'Manager',
                    'approver_type' => 'role',
                    'approver_ref' => $role->name,
                    'min_approvals' => 1,
                ],
            ],
        ])->assertRedirect(route('settings.workflow.index'));

        $workflow = ApprovalWorkflow::firstWhere('name', 'Standard approval');
        $this->assertNotNull($workflow);
        $this->assertSame(1, $workflow->stages()->count());
    }

    public function test_super_admin_can_update_workflow_replacing_stages(): void
    {
        $admin = $this->makeSuperAdmin();
        $role = Role::firstWhere('name', 'admin') ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $workflow = ApprovalWorkflow::create([
            'name' => 'Old WF',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Initial',
            'approver_type' => 'role',
            'approver_ref' => $role->name,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.workflow.update', $workflow), [
            'name' => 'Renamed WF',
            'document_type' => 'generic',
            'is_active' => 1,
            'stages' => [
                ['step_no' => 1, 'name' => 'Updated A', 'approver_type' => 'role', 'approver_ref' => $role->name, 'min_approvals' => 1],
                ['step_no' => 2, 'name' => 'Updated B', 'approver_type' => 'role', 'approver_ref' => $role->name, 'min_approvals' => 1],
            ],
        ])->assertRedirect(route('settings.workflow.edit', $workflow));

        $workflow->refresh();
        $this->assertSame('Renamed WF', $workflow->name);
        $this->assertSame(2, $workflow->stages()->count());
    }

    public function test_super_admin_can_destroy_unused_workflow(): void
    {
        $admin = $this->makeSuperAdmin();
        $workflow = ApprovalWorkflow::create([
            'name' => 'Goner',
            'document_type' => 'generic',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.workflow.destroy', $workflow))
            ->assertRedirect(route('settings.workflow.index'));

        $this->assertNull($workflow->fresh());
    }

    public function test_create_rejects_unknown_role(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.workflow.store'), [
            'name' => 'Invalid',
            'document_type' => 'generic',
            'stages' => [
                ['step_no' => 1, 'name' => 'X', 'approver_type' => 'role', 'approver_ref' => 'nonexistent-role', 'min_approvals' => 1],
            ],
        ])->assertSessionHasErrors('stages.0.approver_ref');

        $this->assertSame(0, ApprovalWorkflow::where('name', 'Invalid')->count());
    }
}
