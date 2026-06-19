<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Demo forms: full field-type showcase in 2- and 3-column layouts.
 *
 * Requires school document types (DocumentTypeSeeder). For approval routing on /forms,
 * run IndustryTemplateSeeder first so workflows exist — policies attach when possible.
 */
class DocumentFormSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['school_leave_request', 'school_procurement'] as $code) {
            if (! DocumentType::query()->where('code', $code)->exists()) {
                $this->command?->warn("DocumentFormSeeder: document type {$code} missing; run DocumentTypeSeeder (and IndustryTemplateSeeder for workflows).");

                return;
            }
        }

        $fields = $this->demoFieldsDefinition();

        $this->syncDemoForm(
            'demo_all_field_types_2col',
            'สาธิตทุกประเภทฟิลด์ — 2 คอลัมน์',
            'school_leave_request',
            'ตัวอย่างเลย์เอาต์ 2 คอลัมน์ ครบทุกประเภทฟิลด์ (text, ตัวเลือก, lookup, ตาราง ฯลฯ)',
            2,
            $fields
        );

        $this->syncDemoForm(
            'demo_all_field_types_3col',
            'สาธิตทุกประเภทฟิลด์ — 3 คอลัมน์',
            'school_procurement',
            'ตัวอย่างเลย์เอาต์ 3 คอลัมน์ ครบทุกประเภทฟิลด์ (text, ตัวเลือก, lookup, ตาราง ฯลฯ)',
            3,
            $fields
        );

        $this->command?->info('DocumentFormSeeder: demo_all_field_types_2col + demo_all_field_types_3col.');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function syncDemoForm(string $formKey, string $name, string $documentType, string $description, int $layoutColumns, array $fields): void
    {
        $form = DocumentForm::query()->updateOrCreate(
            ['form_key' => $formKey],
            [
                'name' => $name,
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
                'layout_columns' => $layoutColumns,
            ]
        );

        $form->fields()->delete();

        foreach ($fields as $i => $f) {
            $form->fields()->create([
                'field_key' => $f['field_key'],
                'label' => $f['label'],
                'field_type' => $f['field_type'],
                'is_required' => (bool) ($f['is_required'] ?? false),
                'sort_order' => $i + 1,
                'col_span' => (int) ($f['col_span'] ?? 0),
                'placeholder' => $f['placeholder'] ?? null,
                'options' => $f['options'] ?? null,
            ]);
        }

        $workflow = ApprovalWorkflow::query()
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($workflow) {
            DocumentFormWorkflowPolicy::query()->updateOrCreate(
                [
                    'form_id' => $form->id,
                ],
                [
                    'use_amount_condition' => false,
                    'workflow_id' => $workflow->id,
                ]
            );
        }

        // DatabaseSeeder uses WithoutModelEvents — the observer won't fire from
        // here, so we call the sync helper directly. It respects admin customizations
        // on existing rows (won't overwrite label/icon/parent/sort).
        \App\Models\NavigationMenu::withoutEvents(fn () => DocumentForm::syncNavigationMenu($form));
    }

    /**
     * One instance of each supported field_type (plus section headers).
     *
     * @return list<array<string, mixed>>
     */
    private function demoFieldsDefinition(): array
    {
        return [
            ['field_key' => 'section_basic', 'label' => 'ข้อความและตัวเลข', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_text', 'label' => 'ข้อความ (text)', 'field_type' => 'text', 'is_required' => true, 'placeholder' => 'ข้อความสั้นๆ'],
            ['field_key' => 'demo_textarea', 'label' => 'ข้อความยาว (textarea)', 'field_type' => 'textarea', 'is_required' => false, 'placeholder' => 'รายละเอียด'],
            ['field_key' => 'demo_number', 'label' => 'ตัวเลข (number)', 'field_type' => 'number', 'is_required' => false, 'placeholder' => '0'],
            ['field_key' => 'demo_currency', 'label' => 'จำนวนเงิน (currency)', 'field_type' => 'currency', 'is_required' => false, 'placeholder' => '0.00'],

            ['field_key' => 'section_datetime', 'label' => 'วันและเวลา', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_date', 'label' => 'วันที่ (date)', 'field_type' => 'date', 'is_required' => false],
            ['field_key' => 'demo_time', 'label' => 'เวลา (time)', 'field_type' => 'time', 'is_required' => false],
            ['field_key' => 'demo_datetime', 'label' => 'วันเวลา (datetime)', 'field_type' => 'datetime', 'is_required' => false],

            ['field_key' => 'section_contact', 'label' => 'การติดต่อ', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_email', 'label' => 'อีเมล (email)', 'field_type' => 'email', 'is_required' => false, 'placeholder' => 'name@school.ac.th'],
            ['field_key' => 'demo_phone', 'label' => 'โทรศัพท์ (phone)', 'field_type' => 'phone', 'is_required' => false, 'placeholder' => '081-234-5678'],

            ['field_key' => 'section_choice', 'label' => 'ตัวเลือก', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_select', 'label' => 'รายการแบบเลือก (select)', 'field_type' => 'select', 'is_required' => false, 'options' => ['ตัวเลือก A', 'ตัวเลือก B', 'ตัวเลือก C']],
            ['field_key' => 'demo_radio', 'label' => 'ปุ่มเลือก (radio)', 'field_type' => 'radio', 'is_required' => false, 'options' => ['ใช่', 'ไม่', 'ไม่แน่ใจ']],
            ['field_key' => 'demo_checkbox', 'label' => 'ช่องทำเครื่องหมาย (checkbox)', 'field_type' => 'checkbox', 'is_required' => false, 'options' => ['อ่านแล้ว', 'ยอมรับเงื่อนไข', 'แจ้งผู้บริหาร']],

            ['field_key' => 'section_media', 'label' => 'ไฟล์และลายเซ็น', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_file', 'label' => 'แนบไฟล์ (file)', 'field_type' => 'file', 'is_required' => false],
            ['field_key' => 'demo_signature', 'label' => 'ลายเซ็น (signature)', 'field_type' => 'signature', 'is_required' => false],

            ['field_key' => 'section_lookup_table', 'label' => 'ค้นหาและตาราง', 'field_type' => 'section', 'is_required' => false],
            ['field_key' => 'demo_lookup_user', 'label' => 'ค้นหาผู้ใช้ (lookup)', 'field_type' => 'lookup', 'is_required' => false, 'options' => ['source' => 'user']],
            ['field_key' => 'demo_table', 'label' => 'ตาราง (table)', 'field_type' => 'table', 'is_required' => false, 'options' => [
                'columns' => [
                    ['key' => 'item', 'label' => 'รายการ', 'type' => 'text'],
                    ['key' => 'qty', 'label' => 'จำนวน', 'type' => 'number'],
                    ['key' => 'done', 'label' => 'ทำแล้ว', 'type' => 'checkbox'],
                ],
            ]],
        ];
    }
}
