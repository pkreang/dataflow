<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentType;
use Database\Seeders\DocumentFormSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\FactoryPositionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PurchaseWorkflowSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_permissions_exist_after_seeding(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        foreach (['view_purchase_requests', 'view_purchase_orders', 'purchase_order.create'] as $perm) {
            $this->assertTrue(
                Permission::where('name', $perm)->where('guard_name', 'web')->exists(),
                "Permission {$perm} should exist"
            );
        }
    }

    public function test_approver_role_has_view_purchase_permissions(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $role = \Spatie\Permission\Models\Role::where('name', 'approver')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('view_purchase_requests'));
        $this->assertTrue($role->hasPermissionTo('view_purchase_orders'));
    }

    public function test_document_types_seeded(): void
    {
        $this->seed([DocumentTypeSeeder::class]);

        foreach (['school_leave_request', 'school_procurement', 'school_activity'] as $code) {
            $this->assertDatabaseHas('document_types', ['code' => $code]);
        }
    }

    public function test_document_form_seeder_creates_demo_layout_forms(): void
    {
        $this->seed([DocumentTypeSeeder::class, DocumentFormSeeder::class]);

        $this->assertSame(2, DocumentForm::query()->count());
        $this->assertTrue(DocumentForm::query()->where('form_key', 'demo_all_field_types_2col')->where('layout_columns', 2)->exists());
        $this->assertTrue(DocumentForm::query()->where('form_key', 'demo_all_field_types_3col')->where('layout_columns', 3)->exists());
    }

    public function test_purchase_workflow_seeder_creates_workflows(): void
    {
        DocumentType::updateOrCreate(
            ['code' => 'purchase_request'],
            [
                'label_en' => 'Purchase Request',
                'label_th' => 'ใบขอซื้อ',
                'icon' => 'shopping-cart',
                'sort_order' => 2,
                'routing_mode' => 'hybrid',
                'is_active' => true,
            ]
        );
        DocumentType::updateOrCreate(
            ['code' => 'purchase_order'],
            [
                'label_en' => 'Purchase Order',
                'label_th' => 'ใบสั่งซื้อ',
                'icon' => 'document-check',
                'sort_order' => 3,
                'routing_mode' => 'organization_wide',
                'is_active' => true,
            ]
        );

        $this->seed([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            FactoryPositionSeeder::class,
            PurchaseWorkflowSeeder::class,
        ]);

        $this->assertDatabaseHas('approval_workflows', ['name' => 'PR - Small (≤50k)', 'document_type' => 'purchase_request']);
        $this->assertDatabaseHas('approval_workflows', ['name' => 'PR - Large (>50k)', 'document_type' => 'purchase_request']);
        $this->assertDatabaseHas('approval_workflows', ['name' => 'PO - Standard',     'document_type' => 'purchase_order']);
    }

    public function test_purchase_request_web_route_requires_auth(): void
    {
        $response = $this->get('/purchase-requests');
        $response->assertRedirect();
    }

    public function test_purchase_request_items_table_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('purchase_request_items'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('purchase_order_items'));
    }
}
