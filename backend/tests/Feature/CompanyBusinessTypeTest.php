<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\BusinessTypeLookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ฟอร์มบริษัท (/profile) เรนเดอร์ business_type เป็น dropdown จาก lookup list 'business_type'
 * (seed โดย BusinessTypeLookupSeeder) — เลือกได้, เก็บ code, และคงค่า free-text เดิมไม่ให้หาย.
 */
class CompanyBusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_renders_business_type_as_lookup_dropdown(): void
    {
        $this->seedBase();
        $company = Company::create(['name' => 'ทดสอบ', 'code' => 'TST']);
        app()->setLocale('th');

        $this->actingAsWebSession($this->makeSuperAdmin())
            ->get('/profile/'.$company->id.'/edit')
            ->assertOk()
            ->assertSee('<select name="business_type"', false)
            ->assertSee('value="manufacturing"', false)
            ->assertSee('การผลิต')          // label ภาษาไทย
            ->assertDontSee('type="text" name="business_type"', false);
    }

    public function test_legacy_free_text_value_is_preserved_as_option(): void
    {
        $this->seedBase();
        $company = Company::create([
            'name' => 'ทดสอบ', 'code' => 'TST', 'business_type' => 'ของเดิมพิมพ์เอง',
        ]);

        $this->actingAsWebSession($this->makeSuperAdmin())
            ->get('/profile/'.$company->id.'/edit')
            ->assertOk()
            ->assertSee('value="ของเดิมพิมพ์เอง" selected', false);
    }

    public function test_update_persists_selected_business_type_code(): void
    {
        $this->seedBase();
        $company = Company::create(['name' => 'ทดสอบ', 'code' => 'TST']);

        $this->actingAsWebSession($this->makeSuperAdmin())
            ->put('/profile/'.$company->id, [
                'code' => 'TST',
                'name' => 'ทดสอบ',
                'business_type' => 'manufacturing',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('manufacturing', $company->fresh()->business_type);
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, BusinessTypeLookupSeeder::class]);
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'first_name' => 'Comp',
            'last_name' => 'Admin',
            'email' => 'company-admin@example.test',
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
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
