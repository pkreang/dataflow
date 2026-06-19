<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Org-model consolidation — form visibility scoped by org_unit.
 * scopeVisibleToUser(orgUnitId): เห็นเมื่อ (ไม่มี restriction) | org ตรง.
 * ดู doc/org-model-consolidation-spec.md
 */
class FormVisibilityOrgUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_restriction_visible_to_everyone(): void
    {
        $form = DocumentForm::factory()->create(['is_active' => true]);

        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser(999)->exists());
        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser(null)->exists());
    }

    public function test_org_unit_restriction_scopes_visibility(): void
    {
        $orgA = OrgUnit::create(['name' => 'Org A', 'type' => 'department', 'is_active' => true]);
        $orgB = OrgUnit::create(['name' => 'Org B', 'type' => 'department', 'is_active' => true]);
        $form = DocumentForm::factory()->create(['is_active' => true]);
        $form->orgUnits()->sync([$orgA->id]);

        $this->assertTrue(DocumentForm::query()->whereKey($form->id)->visibleToUser($orgA->id)->exists());
        $this->assertFalse(DocumentForm::query()->whereKey($form->id)->visibleToUser($orgB->id)->exists());
        // มี org restriction → ผู้ไม่มี org ไม่เห็น (ไม่ตกเข้า no-restriction branch)
        $this->assertFalse(DocumentForm::query()->whereKey($form->id)->visibleToUser(null)->exists());
    }
}
