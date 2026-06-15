<?php

namespace Database\Seeders;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use Illuminate\Database\Seeder;

class EvaluationFormSeeder extends Seeder
{
    /**
     * Idempotent — creates/updates the singleton evaluation form that
     * requesters fill out after their submission is approved.
     */
    public function run(): void
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => 'evaluation_default'],
            [
                'name' => 'แบบประเมินหลังดำเนินการ',
                'document_type' => 'evaluation',
                'description' => 'ประเมินผลการให้บริการหลังงานเสร็จสมบูรณ์',
                'is_active' => true,
                'layout_columns' => 1,
            ]
        );

        $fields = [
            [
                'field_key' => 'overall_rating',
                'label' => 'ความพึงพอใจโดยรวม',
                'field_type' => 'radio',
                'is_required' => true,
                'sort_order' => 1,
                'options' => [
                    '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม',
                    '4 — ⭐⭐⭐⭐ พอใจมาก',
                    '3 — ⭐⭐⭐ พอใจ',
                    '2 — ⭐⭐ ไม่ค่อยพอใจ',
                    '1 — ⭐ ไม่พอใจ',
                ],
            ],
            [
                'field_key' => 'comment',
                'label' => 'ความคิดเห็น',
                'field_type' => 'textarea',
                'is_required' => false,
                'sort_order' => 2,
                'placeholder' => 'แบ่งปันประสบการณ์ของคุณ (ไม่บังคับ)',
                'options' => null,
            ],
            [
                'field_key' => 'improvement',
                'label' => 'สิ่งที่ควรปรับปรุง',
                'field_type' => 'textarea',
                'is_required' => false,
                'sort_order' => 3,
                'placeholder' => 'ข้อเสนอแนะเพื่อปรับปรุงในครั้งต่อไป (ไม่บังคับ)',
                'options' => null,
            ],
        ];

        foreach ($fields as $f) {
            DocumentFormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $f['field_key']],
                [
                    'label' => $f['label'],
                    'field_type' => $f['field_type'],
                    'is_required' => (bool) $f['is_required'],
                    'sort_order' => $f['sort_order'],
                    'col_span' => 0,
                    'placeholder' => $f['placeholder'] ?? null,
                    'options' => $f['options'] ?? null,
                ]
            );
        }
    }
}
