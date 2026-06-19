<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\DocumentFormSubmissionController;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Org-model consolidation Phase 2c — field-level visibility org_unit-first, dept fallback.
 * fieldVisibleToUser(field, orgUnitId, deptId): เห็นเมื่อ (ไม่มี restriction) | org ตรง | dept ตรง.
 * ทดสอบ reader logic ตรงผ่าน reflection (private method). ดู spec.
 */
class FieldVisibilityOrgUnitTest extends TestCase
{
    use RefreshDatabase;

    private function visible(DocumentFormField $field, ?int $org, ?int $dept): bool
    {
        $m = new ReflectionMethod(DocumentFormSubmissionController::class, 'fieldVisibleToUser');
        $m->setAccessible(true);

        return $m->invoke(app(DocumentFormSubmissionController::class), $field, $org, $dept);
    }

    private function field(array $attrs = []): DocumentFormField
    {
        $form = DocumentForm::factory()->create(['is_active' => true]);

        return DocumentFormField::create(array_merge([
            'form_id' => $form->id, 'field_key' => 'f'.uniqid(), 'label' => 'F',
            'field_type' => 'text', 'sort_order' => 1,
        ], $attrs));
    }

    public function test_no_restriction_visible_to_all(): void
    {
        $field = $this->field(['visible_to_org_units' => null, 'visible_to_departments' => null]);
        $this->assertTrue($this->visible($field, 5, 7));
        $this->assertTrue($this->visible($field, null, null));
    }

    public function test_org_unit_restriction_scopes(): void
    {
        $field = $this->field(['visible_to_org_units' => [10], 'visible_to_departments' => null]);
        $this->assertTrue($this->visible($field, 10, null));
        $this->assertFalse($this->visible($field, 11, null));
        $this->assertFalse($this->visible($field, null, 999));
    }

    public function test_department_fallback_preserved(): void
    {
        $field = $this->field(['visible_to_org_units' => null, 'visible_to_departments' => [3]]);
        $this->assertTrue($this->visible($field, null, 3));
        $this->assertFalse($this->visible($field, null, 4));
    }

    public function test_org_or_dept_match_grants_visibility(): void
    {
        $field = $this->field(['visible_to_org_units' => [10], 'visible_to_departments' => [3]]);
        $this->assertTrue($this->visible($field, 10, 999));  // org match
        $this->assertTrue($this->visible($field, 999, 3));   // dept match
        $this->assertFalse($this->visible($field, 999, 999)); // neither
    }
}
