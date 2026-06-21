<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Models\SystemChangeLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemChangeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_change_creates_log_row(): void
    {
        $this->seedBase();
        Setting::set('foo_demo', 'bar');

        $row = SystemChangeLog::query()
            ->where('entity_type', 'setting')
            ->where('entity_id', 'foo_demo')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('created', $row->action);
        $this->assertSame('bar', $row->changed_fields['value']['to'] ?? null);
        // 'from' should be null/absent on creation (depending on JSON serialiser
        // behavior — MySQL JSON columns sometimes drop top-level null entries).
        $this->assertEmpty($row->changed_fields['value']['from'] ?? null);
    }

    public function test_setting_unchanged_value_does_not_log(): void
    {
        $this->seedBase();
        Setting::set('foo_same', 'bar');
        SystemChangeLog::query()->delete();   // wipe initial 'created' row

        Setting::set('foo_same', 'bar');      // same value again

        $this->assertSame(0, SystemChangeLog::query()->where('entity_id', 'foo_same')->count());
    }

    public function test_setting_changed_value_logs_update(): void
    {
        $this->seedBase();
        Setting::set('foo_change', 'before');
        SystemChangeLog::query()->delete();

        Setting::set('foo_change', 'after');

        $row = SystemChangeLog::query()->where('entity_id', 'foo_change')->first();
        $this->assertNotNull($row);
        $this->assertSame('updated', $row->action);
        $this->assertSame('before', $row->changed_fields['value']['from']);
        $this->assertSame('after', $row->changed_fields['value']['to']);
    }

    public function test_workflow_stage_update_records_changed_fields_only(): void
    {
        $this->seedBase();
        $workflow = ApprovalWorkflow::create([
            'name' => 'WF', 'document_type' => 'sys_chg_test', 'is_active' => true,
        ]);
        $stage = ApprovalWorkflowStage::create([
            'workflow_id' => $workflow->id,
            'step_no' => 1, 'name' => 'S', 'approver_type' => 'user',
            'approver_ref' => '1', 'min_approvals' => 1, 'is_active' => true,
        ]);
        SystemChangeLog::query()->delete();   // ignore the create row

        $stage->update(['min_approvals' => 2]);

        $row = SystemChangeLog::query()->where('entity_type', 'workflow_stage')->latest('created_at')->first();
        $this->assertNotNull($row);
        $this->assertSame('updated', $row->action);
        $this->assertArrayHasKey('min_approvals', $row->changed_fields);
        $this->assertSame(1, (int) $row->changed_fields['min_approvals']['from']);
        $this->assertSame(2, (int) $row->changed_fields['min_approvals']['to']);
        // Unchanged columns should NOT be in the diff
        $this->assertArrayNotHasKey('name', $row->changed_fields);
        $this->assertArrayNotHasKey('step_no', $row->changed_fields);
    }

    public function test_document_type_routing_mode_change_logged(): void
    {
        $this->seedBase();
        $type = DocumentType::create([
            'code' => 'sys_chg_doc',
            'label_en' => 'Sys Change Doc', 'label_th' => 'Sys',
            'is_active' => true,
            'routing_mode' => 'hybrid',
        ]);
        SystemChangeLog::query()->delete();

        $type->update(['routing_mode' => 'department_scoped']);

        $row = SystemChangeLog::query()->where('entity_type', 'document_type')->latest('created_at')->first();
        $this->assertNotNull($row);
        $this->assertSame('hybrid', $row->changed_fields['routing_mode']['from']);
        $this->assertSame('department_scoped', $row->changed_fields['routing_mode']['to']);
    }

    public function test_index_route_requires_super_admin(): void
    {
        $this->seedBase();
        $regular = User::create([
            'first_name' => 'Reg', 'last_name' => 'User',
            'email' => 'reg_'.uniqid().'@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => false,
        ]);

        $this->actingAsWebSession($regular)
            ->get(route('settings.system-change-log'))
            ->assertForbidden();
    }

    public function test_index_route_renders_for_super_admin(): void
    {
        $this->seedBase();
        $admin = User::create([
            'first_name' => 'Super', 'last_name' => 'Admin',
            'email' => 'sa_'.uniqid().'@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => true,
        ]);
        Setting::set('foo_renderable', 'bar');     // produce one row

        $this->actingAsWebSession($admin)
            ->get(route('settings.system-change-log'))
            ->assertOk()
            ->assertSee('foo_renderable');
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-scl')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => [],
        ]);
    }
}
