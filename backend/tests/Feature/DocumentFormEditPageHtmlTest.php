<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentType;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Regression: the toolbar partial _form-fixed-primary-actions contains a
 * nested <form> for the "Create Report" button (edit-mode only). HTML5
 * parsing closes the outer form when it encounters a nested <form>, so
 * the toolbar MUST stay outside the outer document-form-builder <form>
 * tag. If a refactor accidentally puts the toolbar back inside the outer
 * form, all required inputs (form_key, name, document_type, table_name,
 * fields[]) would fall outside and Save would POST empty body.
 */
class DocumentFormEditPageHtmlTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_edit_page_required_inputs_are_inside_outer_form(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = $this->makeSuperAdmin();

        $form = DocumentForm::create([
            'form_key' => 'sample_form',
            'name' => 'Sample',
            'document_type' => 'generic',
            'is_active' => true,
            'layout_columns' => 2,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id,
            'field_key' => 'title',
            'label' => 'Title',
            'field_type' => 'text',
            'sort_order' => 1,
        ]);

        $html = $this->actingAsWebSession($admin)
            ->get(route('settings.document-forms.edit', $form))
            ->assertOk()
            ->getContent();

        $formStart = strpos($html, '<form id="document-form-builder"');
        $this->assertNotFalse($formStart, 'Outer form tag must exist');

        $formEnd = strpos($html, '</form>', $formStart);
        $this->assertNotFalse($formEnd, 'Outer form closing tag must exist');

        $formContent = substr($html, $formStart, $formEnd - $formStart);

        foreach (['form_key', 'name', 'document_type', 'table_name'] as $name) {
            $this->assertStringContainsString(
                'name="'.$name.'"',
                $formContent,
                "Input name=\"{$name}\" must be inside the outer form-builder <form>"
            );
        }
    }

    public function test_super_admin_can_save_edit_with_existing_table(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = $this->makeSuperAdmin();

        DocumentType::create([
            'code' => 'generic',
            'label_en' => 'Generic',
            'label_th' => 'ทั่วไป',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $form = DocumentForm::create([
            'form_key' => 'edit_save_form',
            'name' => 'Edit Save Form',
            'document_type' => 'generic',
            'submission_table' => 'edit_save_form',
            'is_active' => true,
            'layout_columns' => 2,
        ]);

        $payload = [
            'form_key' => $form->form_key,
            'name' => 'Renamed via edit',
            'document_type' => 'generic',
            'description' => 'Updated description',
            'is_active' => '1',
            'layout_columns' => '2',
            'table_name' => $form->submission_table,
            'fields' => [
                [
                    'field_key' => 'title',
                    'label' => 'Title',
                    'label_en' => 'Title',
                    'label_th' => 'หัวข้อ',
                    'field_type' => 'text',
                    'is_required' => '1',
                    'col_span' => '0',
                ],
            ],
        ];

        $this->actingAsWebSession($admin)
            ->put(route('settings.document-forms.update', $form), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('Renamed via edit', $form->fresh()->name);
    }
}
