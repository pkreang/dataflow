<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_code_generated_on_create_for_each_entity(): void
    {
        $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
        $pos  = Position::create(['name' => 'Manager', 'code' => 'MGR']);
        $cat  = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP']);
        $loc  = EquipmentLocation::create(['name' => 'Bldg A', 'code' => 'BLDA']);

        $this->assertSame('DEPT-001', $dept->auto_code);
        $this->assertSame('POS-001', $pos->auto_code);
        $this->assertSame('EQCAT-001', $cat->auto_code);
        $this->assertSame('EQLOC-001', $loc->auto_code);
    }

    public function test_auto_code_increments_per_entity_independently(): void
    {
        $d1 = Department::create(['name' => 'A', 'code' => 'A']);
        $d2 = Department::create(['name' => 'B', 'code' => 'B']);
        $d3 = Department::create(['name' => 'C', 'code' => 'C']);
        $p1 = Position::create(['name' => 'X', 'code' => 'X']);
        $p2 = Position::create(['name' => 'Y', 'code' => 'Y']);

        $this->assertSame(['DEPT-001', 'DEPT-002', 'DEPT-003'], [$d1->auto_code, $d2->auto_code, $d3->auto_code]);
        $this->assertSame(['POS-001', 'POS-002'], [$p1->auto_code, $p2->auto_code]);
    }

    public function test_auto_code_skips_after_delete_does_not_reuse(): void
    {
        $d1 = Department::create(['name' => 'A', 'code' => 'A']);
        $d2 = Department::create(['name' => 'B', 'code' => 'B']);
        $d3 = Department::create(['name' => 'C', 'code' => 'C']);

        $d2->delete();

        $d4 = Department::create(['name' => 'D', 'code' => 'D']);

        $this->assertSame('DEPT-004', $d4->auto_code);
    }

    public function test_code_user_input_normalize_runs_before_unique_check(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        Department::create(['name' => 'Existing', 'code' => 'IT']);

        // Lowercase 'it' normalizes to 'IT' → must produce friendly 422,
        // not a 500 from DB-level unique violation.
        $resp = $this->actingAsWebSession($admin)->post(route('settings.departments.store'), [
            'name' => 'Duplicate',
            'code' => 'it',
        ]);

        $resp->assertSessionHasErrors('code');
        $this->assertSame(1, Department::where('code', 'IT')->count());
    }

    public function test_auto_code_cannot_be_overridden_via_request(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $resp = $this->actingAsWebSession($admin)->post(route('settings.departments.store'), [
            'name' => 'IT',
            'code' => 'IT',
            'auto_code' => 'DEPT-999', // attacker-supplied — should be ignored
        ]);

        $resp->assertSessionHasNoErrors();
        $created = Department::where('code', 'IT')->first();
        $this->assertNotNull($created);
        $this->assertSame('DEPT-001', $created->auto_code);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'first_name' => 'Auto',
            'last_name' => 'Admin',
            'email' => 'autocode-admin@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'department_id' => $user->department_id,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
