<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\LookupList;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupListCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_list_with_items(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.lookups.store'), [
            'key' => 'vehicle_brand',
            'label_en' => 'Vehicle Brand',
            'label_th' => 'ยี่ห้อรถ',
            'is_active' => 1,
            'sort_order' => 0,
            'items' => [
                ['value' => 'toyota', 'label_en' => 'Toyota', 'label_th' => 'โตโยต้า', 'is_active' => 1, 'sort_order' => 0, 'extra' => ''],
                ['value' => 'honda', 'label_en' => 'Honda', 'label_th' => 'ฮอนด้า', 'is_active' => 1, 'sort_order' => 1, 'extra' => '{"color":"red"}'],
            ],
        ]);

        $response->assertRedirect(route('settings.lookups.index'));
        $list = LookupList::where('key', 'vehicle_brand')->firstOrFail();
        $this->assertSame(2, $list->items->count());
        $this->assertSame(['color' => 'red'], $list->items->firstWhere('value', 'honda')->extra);
    }

    public function test_cannot_use_built_in_key_collision(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.lookups.store'), [
            'key' => 'user', // collides with built-in
            'label_en' => 'Users',
            'label_th' => 'ผู้ใช้',
            'items' => [],
        ]);
        $response->assertSessionHasErrors('key');
    }

    public function test_duplicate_item_values_within_same_list_are_rejected(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.lookups.store'), [
            'key' => 'reasons',
            'label_en' => 'Reasons',
            'label_th' => 'เหตุผล',
            'items' => [
                ['value' => 'x', 'label_en' => 'X', 'label_th' => 'X', 'is_active' => 1, 'sort_order' => 0],
                ['value' => 'x', 'label_en' => 'X2', 'label_th' => 'X2', 'is_active' => 1, 'sort_order' => 1],
            ],
        ]);
        $response->assertSessionHasErrors();
    }

    public function test_invalid_extra_json_rejected(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.lookups.store'), [
            'key' => 'with_bad_json',
            'label_en' => 'Bad',
            'label_th' => 'Bad',
            'items' => [
                ['value' => 'x', 'label_en' => 'X', 'label_th' => 'X', 'is_active' => 1, 'sort_order' => 0, 'extra' => '{not-json}'],
            ],
        ]);
        $response->assertSessionHasErrors();
    }

    public function test_system_list_cannot_be_deleted(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();
        $list = LookupList::create([
            'key' => 'priority',
            'label_en' => 'Priority',
            'label_th' => 'ระดับ',
            'is_system' => true,
        ]);

        $response = $this->actingAsWebSession($admin)->delete(route('settings.lookups.destroy', $list));
        $response->assertRedirect(route('settings.lookups.index'));
        $response->assertSessionHasErrors('delete');
        $this->assertNotNull($list->fresh());
    }

    public function test_list_in_use_by_form_cannot_be_deleted(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();
        $list = LookupList::create([
            'key' => 'brand',
            'label_en' => 'Brand',
            'label_th' => 'ยี่ห้อ',
        ]);

        $form = DocumentForm::create([
            'form_key' => 'test_form',
            'name' => 'Test Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id,
            'field_key' => 'brand_field',
            'label' => 'Brand',
            'field_type' => 'lookup',
            'sort_order' => 1,
            'options' => ['source' => 'brand'],
        ]);

        $response = $this->actingAsWebSession($admin)->delete(route('settings.lookups.destroy', $list));
        $response->assertSessionHasErrors('delete');
        $this->assertNotNull($list->fresh());
    }

    public function test_non_super_admin_cannot_access_lookups(): void
    {
        $this->seedBase();
        $normal = $this->makeNormalUser();

        $response = $this->actingAsWebSession($normal)->get(route('settings.lookups.index'));
        $response->assertForbidden();
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'lookup-admin@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function makeNormalUser(): User
    {
        return User::create([
            'first_name' => 'User',
            'last_name' => 'Normal',
            'email' => 'lookup-user@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
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
