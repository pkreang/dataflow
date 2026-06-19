<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\DocumentFormSubmissionController;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Org-model consolidation — field-level visibility scoped by org_unit.
 * fieldVisibleToUser(field, orgUnitId): เห็นเมื่อ (ไม่มี restriction) | org ตรง.
 * ทดสอบ reader logic ตรงผ่าน reflection (private method). ดู spec.
 */
class FieldVisibilityOrgUnitTest extends TestCase
{
    use RefreshDatabase;

    private function visible(DocumentFormField $field, ?int $org): bool
    {
        $m = new ReflectionMethod(DocumentFormSubmissionController::class, 'fieldVisibleToUser');
        $m->setAccessible(true);

        return $m->invoke(app(DocumentFormSubmissionController::class), $field, $org);
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
        $field = $this->field(['visible_to_org_units' => null]);
        $this->assertTrue($this->visible($field, 5));
        $this->assertTrue($this->visible($field, null));
    }

    public function test_org_unit_restriction_scopes(): void
    {
        $field = $this->field(['visible_to_org_units' => [10]]);
        $this->assertTrue($this->visible($field, 10));
        $this->assertFalse($this->visible($field, 11));
        $this->assertFalse($this->visible($field, null));
    }
}
