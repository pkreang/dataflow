<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests the model factories: every factory must produce a row that
 * persists against the real schema, and the states must apply cleanly.
 */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_creates_persisted_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotEmpty($user->first_name);
        $this->assertNotEmpty($user->email);
        $this->assertFalse($user->is_super_admin);
        $this->assertTrue($user->is_active);
    }

    public function test_user_factory_states(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->assertTrue($admin->is_super_admin);

        $u = User::factory()->inactive()->unverified()->create();
        $this->assertFalse($u->is_active);
        $this->assertNull($u->email_verified_at);
    }

    public function test_department_factory(): void
    {
        $d = Department::factory()->create();

        $this->assertNotNull($d->id);
        $this->assertNotEmpty($d->code);
    }

    public function test_company_factory(): void
    {
        $c = Company::factory()->create();

        $this->assertNotNull($c->id);
        $this->assertNotEmpty($c->code);
    }

    public function test_document_form_factory(): void
    {
        $f = DocumentForm::factory()->create();

        $this->assertNotNull($f->id);
        $this->assertNotEmpty($f->form_key);
    }

    public function test_document_form_field_factory_builds_its_own_parent(): void
    {
        $field = DocumentFormField::factory()->create();

        $this->assertNotNull($field->id);
        $this->assertNotNull($field->form_id);
        $this->assertSame('text', $field->field_type);
    }

    public function test_document_form_with_fields_relationship(): void
    {
        $form = DocumentForm::factory()
            ->has(DocumentFormField::factory()->count(3), 'fields')
            ->create();

        $this->assertCount(3, $form->fields);
    }

    public function test_document_form_field_select_state(): void
    {
        $field = DocumentFormField::factory()->select(['X', 'Y'])->create();

        $this->assertSame('select', $field->field_type);
        $this->assertSame(['X', 'Y'], $field->options);
    }

    public function test_document_form_submission_factory(): void
    {
        $sub = DocumentFormSubmission::factory()->create();

        $this->assertNotNull($sub->id);
        $this->assertNotNull($sub->form_id);
        $this->assertNotNull($sub->user_id);
        $this->assertSame('draft', $sub->status);
    }

    public function test_document_form_submission_submitted_state(): void
    {
        $sub = DocumentFormSubmission::factory()->submitted()->create();

        $this->assertSame('submitted', $sub->status);
    }
}
