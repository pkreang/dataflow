<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Org-model consolidation Phase 2b — form visibility อ่าน org_unit ก่อน department.
 * scopeVisibleToUser(orgUnitId, departmentId): เห็นเมื่อ (ไม่มี restriction) | org ตรง | dept ตรง.
 * ดู doc/org-model-consolidation-spec.md
 */
class FormVisibilityOrgUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_restriction_visible_to_everyone(): void
    {
        $form = DocumentForm::factory()->create(['is_active' => true]);

        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser(999, 999)->exists());
        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser(null, null)->exists());
    }

    public function test_org_unit_restriction_scopes_visibility(): void
    {
        $orgA = OrgUnit::create(['name' => 'Org A', 'type' => 'department', 'is_active' => true]);
        $orgB = OrgUnit::create(['name' => 'Org B', 'type' => 'department', 'is_active' => true]);
        $form = DocumentForm::factory()->create(['is_active' => true]);
        $form->orgUnits()->sync([$orgA->id]);

        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser($orgA->id, null)->exists());
        $this->assertFalse(DocumentForm::query()->whereKey($form->id)->visibleToUser($orgB->id, null)->exists());
        // มี org restriction → ผู้ไม่มี org ไม่เห็น (ไม่ตกเข้า no-restriction branch)
        $this->assertFalse(DocumentForm::query()->whereKey($form->id)->visibleToUser(null, null)->exists());
    }

    public function test_department_fallback_preserved(): void
    {
        $dept = Department::create(['name' => 'Dept X', 'code' => 'DX'.random_int(1000, 9999), 'is_active' => true]);
        $form = DocumentForm::factory()->create(['is_active' => true]);
        $form->departments()->sync([$dept->id]);

        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser(null, $dept->id)->exists());
        $this->assertFalse(DocumentForm::query()->whereKey($form->id)->visibleToUser(null, 999)->exists());
    }

    public function test_org_match_grants_visibility_without_department(): void
    {
        $org = OrgUnit::create(['name' => 'Org C', 'type' => 'department', 'is_active' => true]);
        $dept = Department::create(['name' => 'Dept Y', 'code' => 'DY'.random_int(1000, 9999), 'is_active' => true]);
        $form = DocumentForm::factory()->create(['is_active' => true]);
        $form->orgUnits()->sync([$org->id]);

        // user มี org ตรง แต่ dept ไม่ตรง → เห็น (org path)
        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser($org->id, 999)->exists());
    }
}
