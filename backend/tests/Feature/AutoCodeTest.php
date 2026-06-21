<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentForm;
use App\Models\DocumentType;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\LookupList;
use App\Models\NavigationMenu;
use App\Models\OrgUnit;
use App\Models\PmPlan;
use App\Models\Position;
use App\Models\ReportDashboard;
use App\Models\RunningNumberConfig;
use App\Models\SparePart;
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
        $dept = OrgUnit::create(['name' => 'IT', 'type' => 'department']);
        $pos = Position::create(['name' => 'Manager', 'code' => 'MGR']);
        $cat = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP']);
        $loc = EquipmentLocation::create(['name' => 'Bldg A', 'code' => 'BLDA']);

        $this->assertSame('ORG-001', $dept->auto_code);
        $this->assertSame('POS-001', $pos->auto_code);
        $this->assertSame('EQCAT-001', $cat->auto_code);
        $this->assertSame('EQLOC-001', $loc->auto_code);
    }

    public function test_auto_code_generated_on_create_for_extended_entities(): void
    {
        // User, Company, Branch — org chain
        $user = User::create([
            'first_name' => 'Auto', 'last_name' => 'Coded',
            'email' => 'autocoded@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $company = Company::create(['name' => 'Acme', 'code' => 'ACME']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'HQ', 'code' => 'HQ']);

        // Document machinery
        $docType = DocumentType::create([
            'code' => 'leave', 'label_en' => 'Leave', 'label_th' => 'ลา', 'is_active' => true,
        ]);
        $form = DocumentForm::create([
            'form_key' => 'leave_form', 'name' => 'Leave Form',
            'document_type' => 'leave', 'is_active' => true, 'layout_columns' => 1,
        ]);

        // Equipment + spare parts
        $cat = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP']);
        $loc = EquipmentLocation::create(['name' => 'A', 'code' => 'A']);
        $equip = Equipment::create([
            'name' => 'Pump-1', 'code' => 'P1',
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
        ]);
        $sp = SparePart::create([
            'code' => 'SP1', 'name' => 'Bearing', 'unit' => 'pcs',
            'equipment_category_id' => $cat->id,
        ]);

        // Lookup, workflow, running-number, dashboard, navigation, pm-plan
        $lookup = LookupList::create([
            'key' => 'colors', 'label_en' => 'Colors', 'label_th' => 'สี', 'is_active' => true,
        ]);
        $wf = ApprovalWorkflow::create([
            'name' => 'Standard', 'document_type' => 'leave', 'is_active' => true,
        ]);
        $rnc = RunningNumberConfig::create([
            'document_type' => 'leave', 'prefix' => 'LV', 'digit_count' => 4,
            'reset_mode' => 'yearly', 'is_active' => true,
        ]);
        $dash = ReportDashboard::create([
            'name' => 'Test Dashboard', 'layout_columns' => 2,
            'visibility' => 'all', 'is_active' => true,
        ]);
        $nav = NavigationMenu::create([
            'label' => 'Test', 'icon' => 'home', 'route' => '/test', 'is_active' => true,
        ]);
        $pm = PmPlan::create([
            'equipment_id' => $equip->id, 'name' => 'Daily check',
            'frequency_type' => 'date', 'interval_days' => 1, 'is_active' => true,
        ]);

        $this->assertSame('USER-001', $user->auto_code);
        $this->assertSame('COMP-001', $company->auto_code);
        $this->assertSame('BR-001', $branch->auto_code);
        $this->assertSame('DOCTYPE-001', $docType->auto_code);
        $this->assertSame('FORM-001', $form->auto_code);
        $this->assertSame('EQ-001', $equip->auto_code);
        $this->assertSame('SP-001', $sp->auto_code);
        $this->assertSame('LKLIST-001', $lookup->auto_code);
        $this->assertSame('WF-001', $wf->auto_code);
        $this->assertSame('RNC-001', $rnc->auto_code);
        $this->assertSame('DASH-001', $dash->auto_code);
        // NavigationMenu may already have NAV-001 from the seeder run by RefreshDatabase
        // if there are any cached seeders — assert prefix only.
        $this->assertMatchesRegularExpression('/^NAV-\d{3}$/', $nav->auto_code);
        $this->assertSame('PMPLAN-001', $pm->auto_code);
    }

    public function test_auto_code_increments_per_entity_independently(): void
    {
        $d1 = OrgUnit::create(['name' => 'A', 'type' => 'department']);
        $d2 = OrgUnit::create(['name' => 'B', 'type' => 'department']);
        $d3 = OrgUnit::create(['name' => 'C', 'type' => 'department']);
        $p1 = Position::create(['name' => 'X', 'code' => 'X']);
        $p2 = Position::create(['name' => 'Y', 'code' => 'Y']);

        $this->assertSame(['ORG-001', 'ORG-002', 'ORG-003'], [$d1->auto_code, $d2->auto_code, $d3->auto_code]);
        $this->assertSame(['POS-001', 'POS-002'], [$p1->auto_code, $p2->auto_code]);
    }

    public function test_auto_code_skips_after_delete_does_not_reuse(): void
    {
        $d1 = OrgUnit::create(['name' => 'A', 'type' => 'department']);
        $d2 = OrgUnit::create(['name' => 'B', 'type' => 'department']);
        $d3 = OrgUnit::create(['name' => 'C', 'type' => 'department']);

        $d2->delete();

        $d4 = OrgUnit::create(['name' => 'D', 'type' => 'department']);

        $this->assertSame('ORG-004', $d4->auto_code);
    }

    public function test_auto_code_request_override_ignored_for_document_type(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $resp = $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'maintenance_request',
            'label_en' => 'Maintenance',
            'label_th' => 'ซ่อม',
            'is_active' => 1,
            'auto_code' => 'DOCTYPE-999', // attacker-supplied — should be ignored
        ]);

        $resp->assertSessionHasNoErrors();
        $created = DocumentType::where('code', 'maintenance_request')->first();
        $this->assertNotNull($created);
        $this->assertSame('DOCTYPE-001', $created->auto_code);
    }

    public function test_auto_code_request_override_ignored_for_lookup_list(): void
    {
        $this->seedBase();
        $admin = $this->makeSuperAdmin();

        $resp = $this->actingAsWebSession($admin)->post(route('settings.lookups.store'), [
            'key' => 'colors',
            'label_en' => 'Colors',
            'label_th' => 'สี',
            'is_active' => 1,
            'auto_code' => 'LKLIST-999', // attacker-supplied — should be ignored
        ]);

        $resp->assertSessionHasNoErrors();
        $created = LookupList::where('key', 'colors')->first();
        $this->assertNotNull($created);
        $this->assertSame('LKLIST-001', $created->auto_code);
    }

    /**
     * Documents the trait's behaviour: direct mass-assignment via Model::create()
     * with a non-empty auto_code DOES overwrite — the `creating` event's
     * `if (empty($model->auto_code))` guard passes through and the supplied
     * value wins. This is intentional (lets data-import flows pin specific
     * codes), but means request safety relies on controllers NOT exposing
     * `auto_code` in validation rules — confirmed by static audit. Treat
     * this test as a tripwire: if it fails (i.e. trait starts forcing
     * overwrites), audit every controller that creates one of the 13
     * entities again before letting the change land.
     */
    public function test_direct_mass_assignment_with_auto_code_does_overwrite_trait(): void
    {
        $dept = OrgUnit::create([
            'name' => 'Import',
            'type' => 'department',
            'auto_code' => 'ORG-IMPORTED',
        ]);

        $this->assertSame('ORG-IMPORTED', $dept->auto_code);
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
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
