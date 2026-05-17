<?php

namespace Tests\Feature\Settings;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class CompaniesProfileCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        Setting::set('company_mode', 'multi');
        Setting::set('branches.enabled', '1');
    }

    public function test_super_admin_can_create_company(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('companies.store'), [
            'name' => 'Acme Co',
            'code' => 'acme',
            'email' => 'hq@acme.test',
            'is_active' => 1,
        ])->assertRedirect(route('companies.index'));

        $co = Company::firstWhere('code', 'acme');
        $this->assertNotNull($co);
        $this->assertSame('Acme Co', $co->name);
    }

    public function test_company_create_blocked_in_single_mode_after_first(): void
    {
        Setting::set('company_mode', 'single');
        $admin = $this->makeSuperAdmin();
        Company::create(['name' => 'First', 'code' => 'first', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('companies.store'), [
            'name' => 'Second',
            'code' => 'second',
        ])->assertRedirect(route('companies.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Company::count());
    }

    public function test_super_admin_can_update_company(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Old', 'code' => 'old', 'is_active' => true]);

        $this->actingAsWebSession($admin)->put(route('companies.update', $co), [
            'name' => 'Renamed',
            'code' => 'old',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('Renamed', $co->fresh()->name);
    }

    public function test_super_admin_can_destroy_company_without_branches(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Goner', 'code' => 'gone', 'is_active' => true]);

        $this->actingAsWebSession($admin)->delete(route('companies.destroy', $co))
            ->assertRedirect(route('companies.index'));

        $this->assertNull($co->fresh());
    }

    public function test_company_with_branches_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Parent', 'code' => 'parent', 'is_active' => true]);
        Branch::create([
            'company_id' => $co->id,
            'name' => 'B1',
            'code' => 'B1',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('companies.destroy', $co))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($co->fresh());
    }

    public function test_super_admin_can_create_branch(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Co', 'code' => 'co', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('companies.branches.store', $co), [
            'branch_name' => 'HQ',
            'branch_code' => 'HQ',
            'branch_is_active' => 1,
        ])->assertRedirect(route('companies.edit', $co));

        $this->assertNotNull(Branch::firstWhere('code', 'HQ'));
    }

    public function test_super_admin_can_update_branch(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Co', 'code' => 'co', 'is_active' => true]);
        $branch = Branch::create([
            'company_id' => $co->id,
            'name' => 'Old',
            'code' => 'OB',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('companies.branches.update', [$co, $branch]), [
            'name' => 'New name',
            'code' => 'OB',
            'is_active' => 1,
        ])->assertRedirect(route('companies.edit', $co));

        $this->assertSame('New name', $branch->fresh()->name);
    }

    public function test_super_admin_can_destroy_branch(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Co', 'code' => 'co', 'is_active' => true]);
        $branch = Branch::create([
            'company_id' => $co->id,
            'name' => 'B',
            'code' => 'B',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('companies.branches.destroy', [$co, $branch]))
            ->assertRedirect(route('companies.edit', $co));

        $this->assertNull($branch->fresh());
    }

    public function test_branch_with_users_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $co = Company::create(['name' => 'Co', 'code' => 'co', 'is_active' => true]);
        $branch = Branch::create([
            'company_id' => $co->id,
            'name' => 'B',
            'code' => 'B',
            'is_active' => true,
        ]);
        User::create([
            'first_name' => 'Holder',
            'last_name' => 'X',
            'email' => 'branch-holder@example.test',
            'password' => 'pw',
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);

        $this->actingAsWebSession($admin)->delete(route('companies.branches.destroy', [$co, $branch]))
            ->assertRedirect(route('companies.edit', $co))
            ->assertSessionHas('error');

        $this->assertNotNull($branch->fresh());
    }

    public function test_branch_mutations_require_manage_profile_permission(): void
    {
        $user = $this->makeRegularUser();
        $co = Company::create(['name' => 'Co', 'code' => 'co', 'is_active' => true]);

        $this->actingAsWebSession($user)->post(route('companies.branches.store', $co), [
            'branch_name' => 'B',
            'branch_code' => 'B',
        ])->assertForbidden();
    }

    public function test_duplicate_company_code_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        Company::create(['name' => 'X', 'code' => 'dup', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('companies.store'), [
            'name' => 'Y',
            'code' => 'dup',
        ])->assertSessionHasErrors('code');
    }
}
