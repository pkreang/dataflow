<?php

namespace Database\Seeders;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentType;
use App\Models\LookupList;
use App\Models\LookupListItem;
use Illuminate\Database\Seeder;

class InternalLetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $type = DocumentType::query()->updateOrCreate(
            ['code' => 'internal_letter'],
            [
                'label_en' => 'Internal letter',
                'label_th' => 'หนังสือภายใน',
                'description' => 'Internal letter template (school / organization).',
                'icon' => 'document-text',
                'sort_order' => 30,
                'routing_mode' => 'organization_wide',
                'is_active' => true,
            ]
        );

        // Lookup lists (DB-driven) for letter speed/confidentiality levels
        $speedList = LookupList::updateOrCreate(
            ['key' => 'letter_speed_level'],
            ['label_en' => 'Letter Speed Level', 'label_th' => 'ชั้นความเร็ว (หนังสือ)', 'is_system' => false, 'is_active' => true, 'sort_order' => 30]
        );
        foreach ([
            ['value' => 'normal',      'label_en' => 'Normal',      'label_th' => 'ปกติ',        'sort_order' => 1],
            ['value' => 'urgent',      'label_en' => 'Urgent',      'label_th' => 'ด่วน',        'sort_order' => 2],
            ['value' => 'very_urgent', 'label_en' => 'Very Urgent', 'label_th' => 'ด่วนมาก',     'sort_order' => 3],
            ['value' => 'most_urgent', 'label_en' => 'Most Urgent', 'label_th' => 'ด่วนที่สุด',  'sort_order' => 4],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $speedList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $confList = LookupList::updateOrCreate(
            ['key' => 'letter_confidentiality_level'],
            ['label_en' => 'Letter Confidentiality Level', 'label_th' => 'ชั้นความลับ (หนังสือ)', 'is_system' => false, 'is_active' => true, 'sort_order' => 31]
        );
        foreach ([
            ['value' => 'normal',       'label_en' => 'Normal',       'label_th' => 'ปกติ',      'sort_order' => 1],
            ['value' => 'confidential', 'label_en' => 'Confidential', 'label_th' => 'ลับ',       'sort_order' => 2],
            ['value' => 'secret',       'label_en' => 'Secret',       'label_th' => 'ลับมาก',    'sort_order' => 3],
            ['value' => 'top_secret',   'label_en' => 'Top Secret',   'label_th' => 'ลับที่สุด', 'sort_order' => 4],
        ] as $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $confList->id, 'value' => $item['value']],
                ['label_en' => $item['label_en'], 'label_th' => $item['label_th'], 'sort_order' => $item['sort_order'], 'is_active' => true]
            );
        }

        $form = DocumentForm::query()->updateOrCreate(
            ['form_key' => 'internal_letter_default'],
            [
                'name' => 'หนังสือภายใน (ตัวอย่าง)',
                'document_type' => $type->code,
                'description' => 'แบบฟอร์มกรอกข้อมูลหนังสือภายใน เพื่อใช้กับหน้าแสดงผล/พิมพ์แบบหนังสือภายใน',
                'is_active' => true,
                'layout_columns' => 1,
            ]
        );

        $fields = [
            ['field_key' => 'section_header', 'label' => 'ส่วนหัวหนังสือ', 'field_type' => 'section'],
            ['field_key' => 'speed_level', 'label' => 'ชั้นความเร็ว (ถ้ามี)', 'field_type' => 'lookup', 'is_required' => false, 'options' => ['source' => 'letter_speed_level']],
            ['field_key' => 'confidentiality_level', 'label' => 'ชั้นความลับ (ถ้ามี)', 'field_type' => 'lookup', 'is_required' => false, 'options' => ['source' => 'letter_confidentiality_level']],
            ['field_key' => 'document_no', 'label' => 'ที่ / เลขที่หนังสือ (1)', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'org_header', 'label' => 'ส่วนราชการ / หน่วยงาน / ที่อยู่ (2)', 'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'document_date', 'label' => 'วัน เดือน ปี (3)', 'field_type' => 'date', 'is_required' => true],

            ['field_key' => 'section_content', 'label' => 'เนื้อหา', 'field_type' => 'section'],
            ['field_key' => 'subject', 'label' => 'เรื่อง (4)', 'field_type' => 'text', 'is_required' => true],
            ['field_key' => 'salutation', 'label' => 'คำขึ้นต้น (5)', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'reference', 'label' => 'อ้างถึง (ถ้ามี) (6)', 'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'attachments_text', 'label' => 'สิ่งที่ส่งมาด้วย (ถ้ามี) (7)', 'field_type' => 'textarea', 'is_required' => false],
            ['field_key' => 'body', 'label' => 'ข้อความ (8)', 'field_type' => 'textarea', 'is_required' => true],

            ['field_key' => 'section_sign', 'label' => 'ลงนาม', 'field_type' => 'section'],
            ['field_key' => 'closing', 'label' => 'คำลงท้าย (9)', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'signature', 'label' => 'ลงชื่อ (ลายเซ็น) (10)', 'field_type' => 'signature', 'is_required' => false],
            ['field_key' => 'sign_name', 'label' => 'พิมพ์ชื่อเต็ม', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'sign_position', 'label' => 'ตำแหน่ง (11)', 'field_type' => 'text', 'is_required' => false],

            ['field_key' => 'section_contact', 'label' => 'ส่วนราชการเจ้าของเรื่อง / ติดต่อ', 'field_type' => 'section'],
            ['field_key' => 'owner_unit', 'label' => 'ส่วนราชการเจ้าของเรื่อง (12)', 'field_type' => 'text', 'is_required' => false],
            ['field_key' => 'phone', 'label' => 'โทร. (13)', 'field_type' => 'phone', 'is_required' => false],
            ['field_key' => 'fax', 'label' => 'โทรสาร', 'field_type' => 'phone', 'is_required' => false],
            ['field_key' => 'cc', 'label' => 'สำเนาส่ง (ถ้ามี) (14)', 'field_type' => 'textarea', 'is_required' => false],
        ];

        $form->fields()->delete();

        foreach (array_values($fields) as $i => $f) {
            DocumentFormField::query()->create([
                'form_id' => $form->id,
                'field_key' => $f['field_key'],
                'label' => $f['label'],
                'field_type' => $f['field_type'],
                'is_required' => (bool) ($f['is_required'] ?? false),
                'sort_order' => $i + 1,
                'col_span' => 0,
                'placeholder' => $f['placeholder'] ?? null,
                'options' => $f['options'] ?? null,
                'editable_by' => null,
            ]);
        }
    }
}
