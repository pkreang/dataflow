<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFormFieldDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_default_keyword_is_persisted(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'date',
            'default_value' => 'today',
        ]));

        $this->assertSame('today', $this->storedField()->default_value);
    }

    public function test_date_default_literal_yyyy_mm_dd_is_persisted(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'date',
            'default_value' => '2026-01-15',
        ]));

        $this->assertSame('2026-01-15', $this->storedField()->default_value);
    }

    public function test_date_default_garbage_is_dropped(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'date',
            'default_value' => 'whenever',
        ]));

        $this->assertNull($this->storedField()->default_value);
    }

    public function test_select_default_in_options_is_persisted(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'select',
            'options_raw' => "Low\nMedium\nHigh",
            'default_value' => 'Medium',
        ]));

        $this->assertSame('Medium', $this->storedField()->default_value);
    }

    public function test_select_default_outside_options_is_dropped(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'select',
            'options_raw' => "Low\nMedium\nHigh",
            'default_value' => 'Critical',
        ]));

        $this->assertNull($this->storedField()->default_value);
    }

    public function test_radio_default_outside_options_is_dropped(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'radio',
            'options_raw' => "Yes\nNo",
            'default_value' => 'Maybe',
        ]));

        $this->assertNull($this->storedField()->default_value);
    }

    public function test_text_default_passes_through(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'text',
            'default_value' => '  hello world  ',
        ]));

        $this->assertSame('hello world', $this->storedField()->default_value);
    }

    public function test_empty_default_persists_as_null(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'text',
            'default_value' => '',
        ]));

        $this->assertNull($this->storedField()->default_value);
    }

    public function test_is_readonly_flag_is_persisted(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'text',
            'is_readonly' => '1',
        ]));

        $this->assertTrue($this->storedField()->is_readonly);
    }

    public function test_is_readonly_defaults_to_false_when_unset(): void
    {
        $this->seedBase();

        $this->actingAsSuperAdmin()->post(route('settings.document-forms.store'), $this->payload([
            'field_type' => 'text',
        ]));

        $this->assertFalse($this->storedField()->is_readonly);
    }

    public function test_localized_label_returns_thai_label_when_locale_is_th(): void
    {
        $field = new DocumentFormField([
            'label' => 'Fallback',
            'label_en' => 'Title',
            'label_th' => 'หัวข้อ',
            'field_type' => 'text',
        ]);

        app()->setLocale('th');

        $this->assertSame('หัวข้อ', $field->localized_label);
    }

    public function test_localized_label_returns_english_label_when_locale_is_en(): void
    {
        $field = new DocumentFormField([
            'label' => 'Fallback',
            'label_en' => 'Title',
            'label_th' => 'หัวข้อ',
            'field_type' => 'text',
        ]);

        app()->setLocale('en');

        $this->assertSame('Title', $field->localized_label);
    }

    public function test_localized_label_falls_back_to_other_locale_then_label_when_target_missing(): void
    {
        // Only label_en filled; locale=th should fall back to label_en, then label
        $onlyEn = new DocumentFormField([
            'label' => 'Generic',
            'label_en' => 'English Only',
            'label_th' => null,
            'field_type' => 'text',
        ]);
        app()->setLocale('th');
        $this->assertSame('English Only', $onlyEn->localized_label);

        // Neither bilingual filled — fall back to legacy `label`
        $legacyOnly = new DocumentFormField([
            'label' => 'Legacy Label',
            'label_en' => null,
            'label_th' => null,
            'field_type' => 'text',
        ]);
        app()->setLocale('th');
        $this->assertSame('Legacy Label', $legacyOnly->localized_label);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides): array
    {
        $field = array_merge([
            'field_key' => 'sample',
            'label' => 'Sample',
            'label_en' => 'Sample',
            'label_th' => 'ตัวอย่าง',
            'field_type' => 'text',
        ], $overrides);

        return [
            'form_key' => 'defaults_form',
            'name' => 'Defaults Form',
            'document_type' => 'generic',
            'layout_columns' => 1,
            'table_name' => 'defaults_form',
            'fields' => [$field],
        ];
    }

    private function storedField(): DocumentFormField
    {
        return DocumentForm::where('form_key', 'defaults_form')
            ->firstOrFail()
            ->fields()
            ->firstOrFail();
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        DocumentType::updateOrCreate(
            ['code' => 'generic'],
            ['label_en' => 'Generic', 'label_th' => 'ทั่วไป', 'is_active' => true]
        );
    }

    private function actingAsSuperAdmin(): self
    {
        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super-defaults@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'is_super_admin' => true,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => [],
        ]);
    }
}
