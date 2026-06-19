<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_normalizes_code_then_persists(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'Maintenance Request',
            'label_en' => 'Maintenance Request',
            'label_th' => 'ใบแจ้งซ่อม',
            'sort_order' => 10,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('settings.document-types.index'));
        $this->assertDatabaseHas('document_types', [
            'code' => 'maintenance_request',
            'label_en' => 'Maintenance Request',
        ]);
    }

    public function test_store_unique_check_runs_after_normalize(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        DocumentType::create([
            'code' => 'maintenance_request',
            'label_en' => 'X',
            'label_th' => 'X',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Raw code with spaces normalizes to existing row's code → should be a
        // friendly 422 (session error), not a DB-level 500.
        $response = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'Maintenance Request',
            'label_en' => 'Dup',
            'label_th' => 'Dup',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, DocumentType::where('code', 'maintenance_request')->count());
    }

    public function test_store_rejects_non_snake_case_code(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'Foo-Bar',
            'label_en' => 'Foo',
            'label_th' => 'Foo',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('document_types', ['code' => 'foo-bar']);
    }

    public function test_store_rejects_unknown_icon(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'good_code',
            'label_en' => 'Good',
            'label_th' => 'Good',
            'icon' => 'totally-fake-icon',
        ]);

        $response->assertSessionHasErrors('icon');
    }

    public function test_store_accepts_known_icon_and_null(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $with = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'with_icon',
            'label_en' => 'With',
            'label_th' => 'With',
            'icon' => 'wrench',
        ]);
        $with->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_types', ['code' => 'with_icon', 'icon' => 'wrench']);

        $without = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'no_icon',
            'label_en' => 'No',
            'label_th' => 'No',
            // icon omitted entirely
        ]);
        $without->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_types', ['code' => 'no_icon', 'icon' => null]);
    }

    public function test_update_unique_ignores_self_and_normalizes(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $type = DocumentType::create([
            'code' => 'old_code',
            'label_en' => 'Old',
            'label_th' => 'Old',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // Resubmitting same code with raw whitespace must not collide with itself.
        $response = $this->actingAsWebSession($admin)->put(
            route('settings.document-types.update', $type),
            [
                'code' => 'Old Code',
                'label_en' => 'Old',
                'label_th' => 'Old',
                'icon' => 'cube',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('settings.document-types.index'));
        $this->assertSame('old_code', $type->fresh()->code);
        $this->assertSame('cube', $type->fresh()->icon);
    }

    public function test_update_rejects_unknown_icon(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $type = DocumentType::create([
            'code' => 'thing',
            'label_en' => 'Thing',
            'label_th' => 'Thing',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAsWebSession($admin)->put(
            route('settings.document-types.update', $type),
            [
                'code' => 'thing',
                'label_en' => 'Thing',
                'label_th' => 'Thing',
                'icon' => 'evil',
            ]
        );

        $response->assertSessionHasErrors('icon');
    }

    public function test_icon_for_returns_active_type_icon(): void
    {
        \Illuminate\Support\Facades\Cache::forget('document_types_active');
        DocumentType::create([
            'code' => 'thing',
            'label_en' => 'Thing',
            'label_th' => 'Thing',
            'icon' => 'wrench',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertSame('wrench', DocumentType::iconFor('thing'));
    }

    public function test_icon_for_returns_null_for_unknown_or_inactive(): void
    {
        \Illuminate\Support\Facades\Cache::forget('document_types_active');
        DocumentType::create([
            'code' => 'hidden',
            'label_en' => 'Hidden',
            'label_th' => 'Hidden',
            'icon' => 'cube',
            'sort_order' => 0,
            'is_active' => false, // inactive — excluded from allActive()
        ]);

        $this->assertNull(DocumentType::iconFor(null));
        $this->assertNull(DocumentType::iconFor(''));
        $this->assertNull(DocumentType::iconFor('does_not_exist'));
        $this->assertNull(DocumentType::iconFor('hidden'));
    }

    public function test_non_super_admin_cannot_access(): void
    {
        $this->seedBase();
        $normal = $this->makeNormalUser();

        $this->actingAsWebSession($normal)
            ->get(route('settings.document-types.index'))
            ->assertForbidden();
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'first_name' => 'Doc',
            'last_name' => 'Admin',
            'email' => 'doctype-admin@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function makeNormalUser(): User
    {
        return User::create([
            'first_name' => 'Doc',
            'last_name' => 'User',
            'email' => 'doctype-user@example.test',
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
