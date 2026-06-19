<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormsMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_shows_forms_bound_to_user_org_unit_and_hides_other_org_unit_forms(): void
    {
        $this->seedBase();

        $orgProd = OrgUnit::create(['name' => 'Production', 'type' => 'department', 'is_active' => true]);
        $orgQc = OrgUnit::create(['name' => 'QC', 'type' => 'department', 'is_active' => true]);

        $prodForm = DocumentForm::create(['form_key' => 'prod_form', 'name' => 'Prod Form', 'document_type' => 'generic', 'is_active' => true]);
        $prodForm->orgUnits()->attach($orgProd->id);

        $qcForm = DocumentForm::create(['form_key' => 'qc_form', 'name' => 'QC Form', 'document_type' => 'generic', 'is_active' => true]);
        $qcForm->orgUnits()->attach($orgQc->id);

        $publicForm = DocumentForm::create(['form_key' => 'public_form', 'name' => 'Public Form', 'document_type' => 'generic', 'is_active' => true]);

        $prodUser = $this->makeUser(['org_unit_id' => $orgProd->id]);

        $menus = app(NavigationService::class)->getMenus([], false, $prodUser->org_unit_id);

        $docsMenu = $menus->firstWhere('label', 'Documents');
        $this->assertNotNull($docsMenu);

        $keys = $docsMenu->children->pluck('route')->toArray();
        $this->assertContains('/forms/prod_form/submissions', $keys);
        $this->assertContains('/forms/public_form/submissions', $keys);
        $this->assertNotContains('/forms/qc_form/submissions', $keys);
    }

    public function test_inactive_forms_are_hidden(): void
    {
        $this->seedBase();

        DocumentForm::create(['form_key' => 'active', 'name' => 'Active', 'document_type' => 'generic', 'is_active' => true]);
        DocumentForm::create(['form_key' => 'inactive', 'name' => 'Inactive', 'document_type' => 'generic', 'is_active' => false]);

        $menus = app(NavigationService::class)->getMenus([], false, null);
        $docs = $menus->firstWhere('label', 'Documents');

        $this->assertNotNull($docs);
        $routes = $docs->children->pluck('route')->toArray();
        $this->assertContains('/forms/active/submissions', $routes);
        $this->assertNotContains('/forms/inactive/submissions', $routes);
    }

    public function test_super_admin_sees_all_forms(): void
    {
        $this->seedBase();

        $org = OrgUnit::create(['name' => 'X', 'type' => 'department', 'is_active' => true]);
        $boundForm = DocumentForm::create(['form_key' => 'bound', 'name' => 'Bound', 'document_type' => 'generic', 'is_active' => true]);
        $boundForm->orgUnits()->attach($org->id);

        // Super-admin with no org_unit still sees the bound form.
        $menus = app(NavigationService::class)->getMenus([], true, null);
        $docs = $menus->firstWhere('label', 'Documents');

        $this->assertNotNull($docs);
        $this->assertContains('/forms/bound/submissions', $docs->children->pluck('route')->toArray());
    }

    public function test_documents_root_hidden_when_no_visible_forms(): void
    {
        $this->seedBase();

        $org = OrgUnit::create(['name' => 'A', 'type' => 'department', 'is_active' => true]);
        $otherOrg = OrgUnit::create(['name' => 'B', 'type' => 'department', 'is_active' => true]);
        $form = DocumentForm::create(['form_key' => 'only_a', 'name' => 'Only A', 'document_type' => 'generic', 'is_active' => true]);
        $form->orgUnits()->attach($org->id);

        $menus = app(NavigationService::class)->getMenus([], false, $otherOrg->id);
        $this->assertNull($menus->firstWhere('label', 'Documents'));
    }

    public function test_list_by_form_route_returns_404_when_form_not_visible_to_user(): void
    {
        $this->seedBase();

        $orgA = OrgUnit::create(['name' => 'AA', 'type' => 'department', 'is_active' => true]);
        $orgB = OrgUnit::create(['name' => 'BB', 'type' => 'department', 'is_active' => true]);

        $restrictedForm = DocumentForm::create(['form_key' => 'restricted', 'name' => 'Restricted', 'document_type' => 'generic', 'is_active' => true]);
        $restrictedForm->orgUnits()->attach($orgA->id);

        $userB = $this->makeUser(['org_unit_id' => $orgB->id]);

        $response = $this->actingAsWebSession($userB)->get('/forms/restricted/submissions');
        $response->assertNotFound();
    }

    public function test_list_by_form_route_returns_200_for_visible_form(): void
    {
        $this->seedBase();

        $form = DocumentForm::create(['form_key' => 'open_form', 'name' => 'Open', 'document_type' => 'generic', 'is_active' => true]);
        $user = $this->makeUser();

        $response = $this->actingAsWebSession($user)->get('/forms/open_form/submissions');
        $response->assertOk();
        $response->assertSee('Open');
    }

    public function test_document_form_save_creates_persistent_navigation_menu_row(): void
    {
        $this->seedBase();

        $form = DocumentForm::create([
            'form_key' => 'persisted_form',
            'name' => 'Persisted Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);

        // Observer persists a real row — admin can now see it in Menu Manager.
        $nav = \App\Models\NavigationMenu::where('document_form_id', $form->id)->first();
        $this->assertNotNull($nav);
        $this->assertSame('/forms/persisted_form/submissions', $nav->route);
        $this->assertSame('Persisted Form', $nav->label);
        $this->assertTrue((bool) $nav->is_active);
    }

    public function test_document_form_rename_updates_route_but_preserves_admin_menu_label(): void
    {
        $this->seedBase();

        $form = DocumentForm::create([
            'form_key' => 'first_key',
            'name' => 'First Name',
            'document_type' => 'generic',
            'is_active' => true,
        ]);

        // Admin renamed the menu row via Menu Manager.
        $nav = \App\Models\NavigationMenu::where('document_form_id', $form->id)->first();
        $nav->update(['label' => 'Admin Pinned Name', 'label_th' => 'ชื่อที่ admin ตั้ง']);

        // Now the underlying form renames + changes form_key.
        $form->update(['form_key' => 'second_key', 'name' => 'Second Name']);

        $nav->refresh();
        // Route follows form_key (clicking must work)
        $this->assertSame('/forms/second_key/submissions', $nav->route);
        // Label preserved — admin's customization wins
        $this->assertSame('Admin Pinned Name', $nav->label);
        $this->assertSame('ชื่อที่ admin ตั้ง', $nav->label_th);
    }

    public function test_document_form_name_flows_into_label_on_first_create(): void
    {
        $this->seedBase();

        $form = DocumentForm::create([
            'form_key' => 'fresh_form',
            'name' => 'Fresh Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);

        $nav = \App\Models\NavigationMenu::where('document_form_id', $form->id)->first();
        $this->assertSame('Fresh Form', $nav->label);
        $this->assertSame('Fresh Form', $nav->label_th);
    }

    public function test_navigation_menu_is_active_toggle_hides_form_from_sidebar(): void
    {
        $this->seedBase();

        $form = DocumentForm::create([
            'form_key' => 'toggleable',
            'name' => 'Toggleable',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        \App\Models\NavigationMenu::where('document_form_id', $form->id)->update(['is_active' => false]);

        \Illuminate\Support\Facades\Cache::forget('navigation_menus_tree');
        $menus = app(\App\Services\NavigationService::class)->getMenus([], false, null);
        $docs = $menus->firstWhere('label', 'Documents');
        $routes = $docs?->children->pluck('route')->toArray() ?? [];
        $this->assertNotContains('/forms/toggleable/submissions', $routes);
    }

    public function test_deleting_document_form_cascades_navigation_menu_row(): void
    {
        $this->seedBase();

        $form = DocumentForm::create([
            'form_key' => 'doomed',
            'name' => 'Doomed',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        $navId = \App\Models\NavigationMenu::where('document_form_id', $form->id)->value('id');
        $this->assertNotNull($navId);

        $form->delete();

        $this->assertNull(\App\Models\NavigationMenu::find($navId));
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeUser(array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => "User{$counter}",
            'email' => "user{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ], $overrides));
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
                'org_unit_id' => $user->org_unit_id,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
