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
 * Reproduce the user-reported case: editing the nteq_maintenance form and
 * pressing Save shows 11 errors (1 reserved key + 7 lookup_source + 3
 * options) despite DB containing valid data.
 *
 * Hypothesis: the form-builder edit page hydrates Alpine state from
 * $initialFields but the lookup_source / options_raw inputs aren't being
 * round-tripped through POST correctly.
 */
class NteqMaintenanceFormSaveTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_resaving_form_with_db_values_should_succeed(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $admin = $this->makeSuperAdmin();
        DocumentType::create([
            'code' => 'maintenance', 'label_en' => 'M', 'label_th' => 'ม', 'sort_order' => 0, 'is_active' => true,
        ]);
        \App\Models\LookupList::create(['key' => 'maintenance_priority', 'label_en' => 'P', 'label_th' => 'ป', 'is_active' => true]);

        $form = DocumentForm::create([
            'form_key' => 'mform', 'name' => 'M', 'document_type' => 'maintenance',
            'submission_table' => 'mform', 'is_active' => true, 'layout_columns' => 2,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'priority', 'label' => 'P', 'label_th' => 'ความเร่งด่วน',
            'field_type' => 'lookup', 'sort_order' => 1,
            'options' => ['source' => 'maintenance_priority'],
        ]);
        \Illuminate\Support\Facades\Cache::forget('lookup_registry_sources');

        // Simulate what the EDIT page POSTs (after Alpine hydrates from DB)
        $payload = [
            'form_key' => 'mform', 'name' => 'Renamed', 'document_type' => 'maintenance',
            'description' => '', 'is_active' => '1', 'layout_columns' => '2', 'table_name' => 'mform',
            'fields' => [
                [
                    'field_key' => 'priority', 'label' => 'P', 'label_th' => 'ความเร่งด่วน',
                    'field_type' => 'lookup', 'is_required' => '1',
                    'lookup_source' => 'maintenance_priority',
                    'col_span' => '0',
                ],
            ],
        ];

        $this->actingAsWebSession($admin)
            ->put(route('settings.document-forms.update', $form), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
    }

    public function test_auto_number_field_may_use_reserved_key_reference_no(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = $this->makeSuperAdmin();

        DocumentType::create([
            'code' => 'maintenance',
            'label_en' => 'Maintenance',
            'label_th' => 'แจ้งซ่อม',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // auto_number is in SKIP_TYPES → doesn't add a DB column → safe to
        // reuse reserved key reference_no, which is the documented purpose
        // (display the system's reference_no value to the requester).
        $form = DocumentForm::create([
            'form_key' => 'nteq_test',
            'name' => 'Test',
            'document_type' => 'maintenance',
            'submission_table' => 'nteq_test',
            'is_active' => true,
            'layout_columns' => 2,
        ]);

        $payload = [
            'form_key' => 'nteq_test',
            'name' => 'Renamed',
            'document_type' => 'maintenance',
            'description' => '',
            'is_active' => '1',
            'layout_columns' => '2',
            'table_name' => 'nteq_test',
            'fields' => [
                [
                    'field_key' => 'reference_no',  // reserved key — allowed for auto_number
                    'label' => 'Ref',
                    'label_th' => 'เลขที่เอกสาร',
                    'field_type' => 'auto_number',
                    'col_span' => '0',
                ],
            ],
        ];

        $this->actingAsWebSession($admin)
            ->put(route('settings.document-forms.update', $form), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();
    }

    public function test_non_auto_number_field_cannot_use_reserved_key(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = $this->makeSuperAdmin();

        DocumentType::create([
            'code' => 'maintenance', 'label_en' => 'M', 'label_th' => 'ม', 'sort_order' => 0, 'is_active' => true,
        ]);

        $form = DocumentForm::create([
            'form_key' => 'reserved_test', 'name' => 'T', 'document_type' => 'maintenance',
            'submission_table' => 'reserved_test', 'is_active' => true, 'layout_columns' => 2,
        ]);

        $this->actingAsWebSession($admin)
            ->put(route('settings.document-forms.update', $form), [
                'form_key' => 'reserved_test', 'name' => 'X', 'document_type' => 'maintenance',
                'is_active' => '1', 'layout_columns' => '2', 'table_name' => 'reserved_test',
                'fields' => [
                    [
                        'field_key' => 'status',  // reserved — text type DOES create column
                        'label' => 'Status', 'label_th' => 'สถานะ',
                        'field_type' => 'text',
                        'col_span' => '0',
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }
}
