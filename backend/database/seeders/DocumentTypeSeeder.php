<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'school_leave_request', 'label_en' => 'Leave / absence request', 'label_th' => 'ลา / ขออนุญาตหยุด', 'icon' => 'calendar-days', 'sort_order' => 20, 'routing_mode' => 'organization_wide'],
            ['code' => 'school_procurement', 'label_en' => 'Small procurement request', 'label_th' => 'ขอซื้อ/จ้าง (วงเงินเล็ก)', 'icon' => 'shopping-bag', 'sort_order' => 21, 'routing_mode' => 'organization_wide'],
            ['code' => 'school_activity', 'label_en' => 'Activity / event approval', 'label_th' => 'ขออนุมัติจัดกิจกรรม', 'icon' => 'academic-cap', 'sort_order' => 22, 'routing_mode' => 'organization_wide'],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'label_en' => $type['label_en'],
                    'label_th' => $type['label_th'],
                    'icon' => $type['icon'],
                    'sort_order' => $type['sort_order'],
                    'routing_mode' => $type['routing_mode'],
                    'is_active' => true,
                ]
            );
        }

        // Add-only: never wipe doc types we don't own. Factory verticals
        // (NTEQ) seed `maintenance_request` independently and used to lose it
        // here whenever this seeder ran out of order.
    }
}
