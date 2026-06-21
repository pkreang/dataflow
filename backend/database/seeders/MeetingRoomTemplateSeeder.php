<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\RunningNumberConfig;
use Illuminate\Database\Seeder;

/**
 * Meeting Room Booking (ใบจองห้องประชุม) template.
 *
 * Idempotent — safe to re-run.
 * Note: no room-conflict detection; approver checks availability manually.
 *
 * Usage:
 *   php artisan db:seed --class=MeetingRoomTemplateSeeder
 */
class MeetingRoomTemplateSeeder extends Seeder
{
    private const DOCUMENT_TYPE = 'meeting_room_booking';

    private const FORM_KEY = 'meeting_room_booking_default';

    public function run(): void
    {
        $this->seedDocumentType();
        $this->seedLookup();
        $this->seedRunningNumber();
        $form = $this->seedForm();
        $workflow = $this->seedWorkflow();
        $this->seedPolicy($form, $workflow);

        $this->command?->info('Meeting Room Booking template ready.');
        $this->command?->info('  Form  : '.self::FORM_KEY.' (id='.$form->id.')');
        $this->command?->info('  Menu  : /forms/'.self::FORM_KEY.'/submissions');
        $this->command?->info('  Rooms : admin can add/edit via Settings > Lookup Lists > meeting_rooms');
    }

    private function seedDocumentType(): void
    {
        DocumentType::firstOrCreate(
            ['code' => self::DOCUMENT_TYPE],
            [
                'label_en' => 'Meeting Room Booking',
                'label_th' => 'ใบจองห้องประชุม',
                'icon' => 'building-office',
                'sort_order' => 55,
                'routing_mode' => 'hybrid',
                'is_active' => true,
            ]
        );
    }

    private function seedLookup(): void
    {
        $list = LookupList::updateOrCreate(
            ['key' => 'meeting_rooms'],
            [
                'label_en' => 'Meeting Rooms',
                'label_th' => 'ห้องประชุม',
                'is_active' => true,
                'sort_order' => 40,
            ]
        );

        $rooms = [
            ['value' => 'room_a', 'label_en' => 'Meeting Room A', 'label_th' => 'ห้องประชุม A', 'sort_order' => 1],
            ['value' => 'room_b', 'label_en' => 'Meeting Room B', 'label_th' => 'ห้องประชุม B', 'sort_order' => 2],
            ['value' => 'room_c', 'label_en' => 'Meeting Room C', 'label_th' => 'ห้องประชุม C', 'sort_order' => 3],
        ];

        foreach ($rooms as $room) {
            LookupListItem::updateOrCreate(
                ['list_id' => $list->id, 'value' => $room['value']],
                [
                    'label_en' => $room['label_en'],
                    'label_th' => $room['label_th'],
                    'sort_order' => $room['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedRunningNumber(): void
    {
        RunningNumberConfig::updateOrCreate(
            ['document_type' => self::DOCUMENT_TYPE],
            [
                'prefix' => 'MRB',
                'digit_count' => 4,
                'reset_mode' => 'yearly',
                'include_year' => true,
                'include_month' => false,
                'is_active' => true,
            ]
        );
    }

    private function seedForm(): DocumentForm
    {
        $form = DocumentForm::updateOrCreate(
            ['form_key' => self::FORM_KEY],
            [
                'name' => 'ใบจองห้องประชุม',
                'document_type' => self::DOCUMENT_TYPE,
                'description' => 'ฟอร์มขอจองห้องประชุม',
                'is_active' => true,
                'layout_columns' => 2,
            ]
        );

        DocumentFormField::query()->where('form_id', $form->id)->delete();

        $fields = [
            [
                'field_key' => 'reference_no',
                'label' => 'เลขที่เอกสาร',
                'label_en' => 'Reference No.',
                'label_th' => 'เลขที่เอกสาร',
                'field_type' => 'auto_number',
                'is_required' => false,
                'is_readonly' => true,
                'sort_order' => 1,
                'col_span' => 2,
            ],
            [
                'field_key' => 'booking_date',
                'label' => 'วันที่ต้องการจอง',
                'label_en' => 'Booking Date',
                'label_th' => 'วันที่ต้องการจอง',
                'field_type' => 'date',
                'is_required' => true,
                'sort_order' => 2,
                'col_span' => 1,
            ],
            [
                'field_key' => 'room',
                'label' => 'ห้องประชุมที่ต้องการ',
                'label_en' => 'Room',
                'label_th' => 'ห้องประชุมที่ต้องการ',
                'field_type' => 'lookup',
                'is_required' => true,
                'sort_order' => 3,
                'col_span' => 1,
                'options' => ['source' => 'meeting_rooms'],
            ],
            [
                'field_key' => 'start_time',
                'label' => 'เวลาเริ่ม',
                'label_en' => 'Start Time',
                'label_th' => 'เวลาเริ่ม',
                'field_type' => 'time',
                'is_required' => true,
                'sort_order' => 4,
                'col_span' => 1,
            ],
            [
                'field_key' => 'end_time',
                'label' => 'เวลาสิ้นสุด',
                'label_en' => 'End Time',
                'label_th' => 'เวลาสิ้นสุด',
                'field_type' => 'time',
                'is_required' => true,
                'sort_order' => 5,
                'col_span' => 1,
            ],
            [
                'field_key' => 'attendees_count',
                'label' => 'จำนวนผู้เข้าร่วม (คน)',
                'label_en' => 'Attendees',
                'label_th' => 'จำนวนผู้เข้าร่วม (คน)',
                'field_type' => 'number',
                'is_required' => true,
                'sort_order' => 6,
                'col_span' => 2,
            ],
            [
                'field_key' => 'purpose',
                'label' => 'วัตถุประสงค์การประชุม',
                'label_en' => 'Purpose',
                'label_th' => 'วัตถุประสงค์การประชุม',
                'field_type' => 'textarea',
                'is_required' => true,
                'sort_order' => 7,
                'col_span' => 2,
            ],
            [
                'field_key' => 'equipment_needed',
                'label' => 'อุปกรณ์ที่ต้องการ',
                'label_en' => 'Equipment Needed',
                'label_th' => 'อุปกรณ์ที่ต้องการ',
                'field_type' => 'multi_select',
                'is_required' => false,
                'sort_order' => 8,
                'col_span' => 2,
                'options' => [
                    ['value' => 'projector',  'label' => 'โปรเจคเตอร์'],
                    ['value' => 'whiteboard', 'label' => 'กระดานไวท์บอร์ด'],
                    ['value' => 'microphone', 'label' => 'ไมโครโฟน'],
                    ['value' => 'screen',     'label' => 'จอภาพ'],
                    ['value' => 'video_conf', 'label' => 'Video Conference'],
                ],
            ],
            [
                'field_key' => 'notes',
                'label' => 'หมายเหตุเพิ่มเติม',
                'label_en' => 'Notes',
                'label_th' => 'หมายเหตุเพิ่มเติม',
                'field_type' => 'textarea',
                'is_required' => false,
                'sort_order' => 9,
                'col_span' => 2,
            ],
            [
                'field_key' => 'signature',
                'label' => 'ลายเซ็นผู้ขอจอง',
                'label_en' => 'Signature',
                'label_th' => 'ลายเซ็นผู้ขอจอง',
                'field_type' => 'signature',
                'is_required' => true,
                'sort_order' => 10,
                'col_span' => 2,
            ],
        ];

        foreach ($fields as $data) {
            DocumentFormField::create(array_merge(['form_id' => $form->id], $data));
        }

        return $form;
    }

    private function seedWorkflow(): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติใบจองห้องประชุม (ค่าเริ่มต้น)'],
            [
                'document_type' => self::DOCUMENT_TYPE,
                'description' => 'workflow อนุมัติใบจองห้องประชุม — ปรับเปลี่ยนได้ที่ตั้งค่า > Workflow',
                'is_active' => true,
            ]
        );

        $workflow->stages()->delete();

        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'ผู้อนุมัติการจองห้อง',
            'approver_type' => 'role',
            'approver_ref' => 'approver',
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        return $workflow;
    }

    private function seedPolicy(DocumentForm $form, ApprovalWorkflow $workflow): void
    {
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id, 'position_id' => null],
            [
                'workflow_id' => $workflow->id,
                'use_amount_condition' => false,
            ]
        );
    }
}
