<?php

namespace Database\Seeders;

use App\Models\LookupList;
use App\Models\LookupListItem;
use Illuminate\Database\Seeder;

/**
 * Lookup list "business_type" — ตัวเลือกประเภทธุรกิจของบริษัท (ฟอร์ม /profile).
 * ดึงไปแสดงเป็น dropdown ผ่าน LookupRegistry::getItems('business_type').
 * super-admin เพิ่ม/แก้ items เองได้ที่ /settings/lookups (idempotent — re-seed ปลอดภัย).
 */
class BusinessTypeLookupSeeder extends Seeder
{
    public function run(): void
    {
        $list = LookupList::updateOrCreate(
            ['key' => 'business_type'],
            [
                'label_en' => 'Business Type',
                'label_th' => 'ประเภทธุรกิจ',
                'description' => 'ประเภทธุรกิจของบริษัท (ใช้ในฟอร์มข้อมูลองค์กร)',
                'is_system' => false,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        $items = [
            ['value' => 'manufacturing',    'label_th' => 'การผลิต',        'label_en' => 'Manufacturing'],
            ['value' => 'distribution',     'label_th' => 'ตัวแทนจำหน่าย',  'label_en' => 'Distribution'],
            ['value' => 'service',          'label_th' => 'งานบริการ',      'label_en' => 'Service'],
            ['value' => 'retail_wholesale', 'label_th' => 'ค้าปลีก-ค้าส่ง',  'label_en' => 'Retail / Wholesale'],
            ['value' => 'import_export',    'label_th' => 'นำเข้า-ส่งออก',   'label_en' => 'Import / Export'],
            ['value' => 'construction',     'label_th' => 'ก่อสร้าง',        'label_en' => 'Construction'],
            ['value' => 'it_software',      'label_th' => 'ไอที / ซอฟต์แวร์', 'label_en' => 'IT / Software'],
            ['value' => 'other',            'label_th' => 'อื่นๆ',          'label_en' => 'Other'],
        ];

        foreach ($items as $i => $item) {
            LookupListItem::updateOrCreate(
                ['list_id' => $list->id, 'value' => $item['value']],
                [
                    'label_th' => $item['label_th'],
                    'label_en' => $item['label_en'],
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
