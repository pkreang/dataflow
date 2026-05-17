<?php

namespace Tests\Feature\Settings;

use App\Models\DocumentForm;
use App\Models\DocumentType;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class DocumentTypesCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_document_type(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'meeting note',
            'label_en' => 'Meeting Note',
            'label_th' => 'บันทึกการประชุม',
            'sort_order' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('settings.document-types.index'));

        $type = DocumentType::firstWhere('code', 'meeting_note');
        $this->assertNotNull($type);
        $this->assertSame('Meeting Note', $type->label_en);
        $this->assertSame(5, $type->sort_order);
    }

    public function test_super_admin_can_update_document_type(): void
    {
        $admin = $this->makeSuperAdmin();
        $type = DocumentType::create([
            'code' => 'orig',
            'label_en' => 'Original',
            'label_th' => 'เดิม',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.document-types.update', $type), [
            'code' => 'orig',
            'label_en' => 'Renamed',
            'label_th' => 'เปลี่ยน',
            'sort_order' => 7,
            'is_active' => 0,
        ])->assertRedirect(route('settings.document-types.index'));

        $type->refresh();
        $this->assertSame('Renamed', $type->label_en);
        $this->assertFalse($type->is_active);
    }

    public function test_super_admin_can_destroy_unused_document_type(): void
    {
        $admin = $this->makeSuperAdmin();
        $type = DocumentType::create([
            'code' => 'removable',
            'label_en' => 'Removable',
            'label_th' => 'ลบได้',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.document-types.destroy', $type))
            ->assertRedirect(route('settings.document-types.index'));

        $this->assertNull($type->fresh());
    }

    public function test_document_type_in_use_by_form_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $type = DocumentType::create([
            'code' => 'in_use',
            'label_en' => 'In Use',
            'label_th' => 'ใช้งาน',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        DocumentForm::create([
            'form_key' => 'sample',
            'name' => 'Sample',
            'document_type' => 'in_use',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.document-types.destroy', $type))
            ->assertRedirect(route('settings.document-types.index'));

        $this->assertNotNull($type->fresh());
    }

    public function test_duplicate_code_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        DocumentType::create([
            'code' => 'dup',
            'label_en' => 'Dup',
            'label_th' => 'ซ้ำ',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.document-types.store'), [
            'code' => 'dup',
            'label_en' => 'X',
            'label_th' => 'X',
        ])->assertSessionHasErrors('code');
    }
}
