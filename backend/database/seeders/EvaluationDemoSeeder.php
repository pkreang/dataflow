<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class EvaluationDemoSeeder extends Seeder
{
    /**
     * สร้างข้อมูลตัวอย่างให้ "รายงานประเมิน" (/reports/evaluations) มีคะแนนแสดง:
     * ใบแจ้งซ่อมที่ปิดงานแล้ว (approved instance) + ใบประเมินความพึงพอใจผูกกลับ
     * ผ่าน parent_submission_id. รายงานอ่าน overall_rating จาก payload ใบประเมิน
     * แล้ว group ตามฟอร์มของใบแม่ → คะแนนเฉลี่ย + by-form + response rate.
     *
     * Idempotent — ข้ามถ้ามีใบประเมินแล้ว.
     * รันเดี่ยว: php artisan db:seed --class=EvaluationDemoSeeder
     */
    public function run(): void
    {
        // ให้มีแบบฟอร์มประเมิน (evaluation_default) ก่อน
        $this->call(EvaluationFormSeeder::class);

        $evalForm = DocumentForm::where('form_key', 'evaluation_default')->first();
        $repairForm = DocumentForm::where('form_key', 'repair_request_default')->first();
        if (! $evalForm || ! $repairForm) {
            $this->command?->warn('evaluation_default / repair_request_default form missing — skip.');

            return;
        }

        // idempotent: ถ้ามีใบประเมินแล้วไม่ต้องสร้างซ้ำ
        if (DocumentFormSubmission::where('form_id', $evalForm->id)->exists()) {
            $this->command?->info('Evaluations already seeded — skip.');

            return;
        }

        $repairWfId = ApprovalWorkflow::where('document_type', 'repair_request')->value('id');
        $orgUnitIds = OrgUnit::where('type', 'department')->pluck('id')->all();
        $userIds = User::whereIn('email', [
            'staff@demo.test', 'head@demo.test', 'manager@demo.test', 'finance@demo.test', 'hr@demo.test',
        ])->pluck('id')->all();

        if (! $repairWfId || empty($orgUnitIds) || empty($userIds)) {
            $this->command?->warn('Missing repair workflow / org units / demo users — skip.');

            return;
        }

        // pool: งานซ่อมที่ปิดแล้ว + คะแนนประเมิน (เอียง 4-5 ดาว สมจริงกับบริการที่ดี)
        $samples = [
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม', 'title' => 'แอร์ห้องประชุมไม่เย็น', 'category' => 'ระบบปรับอากาศ', 'urgency' => 'ปานกลาง', 'location' => 'อาคาร A ชั้น 3', 'comment' => 'ช่างมาตามนัด แก้ไขเรียบร้อย เครื่องกลับมาเย็นปกติ', 'improvement' => ''],
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม', 'title' => 'เครื่องพิมพ์ชั้น 2 กระดาษติด', 'category' => 'อุปกรณ์สำนักงาน', 'urgency' => 'ต่ำ', 'location' => 'อาคาร A ชั้น 2', 'comment' => 'บริการรวดเร็ว แก้จบในครั้งเดียว ประทับใจ', 'improvement' => ''],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก', 'title' => 'ไฟ LED โรงงานดับ 3 จุด', 'category' => 'ระบบไฟฟ้า', 'urgency' => 'ปานกลาง', 'location' => 'โรงงาน ไลน์ B', 'comment' => 'งานเรียบร้อยดี แต่รอคิวนานนิดหน่อย', 'improvement' => 'อยากให้นัดเวลาแม่นขึ้น'],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก', 'title' => 'ประตูกระจกหน้าออฟฟิศปิดไม่สนิท', 'category' => 'งานอาคาร', 'urgency' => 'ต่ำ', 'location' => 'อาคาร A ชั้น 1', 'comment' => 'แก้ปัญหาได้ตามต้องการ ช่างสุภาพ', 'improvement' => ''],
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม', 'title' => 'สายพานลำเลียงไลน์ A มีเสียงดัง', 'category' => 'เครื่องจักร', 'urgency' => 'สูง', 'location' => 'โรงงาน ไลน์ A', 'comment' => 'ช่างมืออาชีพ อธิบายสาเหตุชัดเจน', 'improvement' => ''],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก', 'title' => 'คอมพิวเตอร์แผนกบัญชีเปิดไม่ติด', 'category' => 'อุปกรณ์ IT', 'urgency' => 'สูง', 'location' => 'อาคาร A ชั้น 4', 'comment' => 'กู้ข้อมูลได้ครบ ใช้งานต่อได้', 'improvement' => 'อยากได้เครื่องสำรองระหว่างซ่อม'],
            ['rating' => '3 — ⭐⭐⭐ พอใจ', 'title' => 'ก๊อกน้ำห้องน้ำชั้น 1 รั่ว', 'category' => 'งานประปา', 'urgency' => 'ปานกลาง', 'location' => 'อาคาร A ชั้น 1', 'comment' => 'แก้เฉพาะหน้าได้ แต่ยังซึมเล็กน้อย', 'improvement' => 'ควรเปลี่ยนอะไหล่ใหม่ทั้งชุด'],
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม', 'title' => 'ปั๊มน้ำหอพักไม่ทำงาน', 'category' => 'งานประปา', 'urgency' => 'สูง', 'location' => 'หอพัก', 'comment' => 'มาช่วยเร่งด่วนนอกเวลา ขอบคุณมาก', 'improvement' => ''],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก', 'title' => 'เครื่องปรับอากาศห้อง Server', 'category' => 'ระบบปรับอากาศ', 'urgency' => 'สูง', 'location' => 'ห้อง Server', 'comment' => 'ดูแลดี ตรวจเช็คให้ครบทุกจุด', 'improvement' => ''],
            ['rating' => '2 — ⭐⭐ ไม่ค่อยพอใจ', 'title' => 'ลิฟต์ขนของค้างชั้น 3', 'category' => 'งานอาคาร', 'urgency' => 'สูง', 'location' => 'อาคาร B', 'comment' => 'ใช้เวลานานกว่าจะมา และต้องตามหลายรอบ', 'improvement' => 'ควรมีช่างเวรประจำที่ตอบสนองเร็วกว่านี้'],
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม', 'title' => 'หลังคาโรงอาหารรั่วซึม', 'category' => 'งานอาคาร', 'urgency' => 'ปานกลาง', 'location' => 'โรงอาหาร', 'comment' => 'ซ่อมเรียบร้อย ไม่รั่วอีกเลย', 'improvement' => ''],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก', 'title' => 'ระบบ WiFi ชั้น 4 ใช้ไม่ได้', 'category' => 'อุปกรณ์ IT', 'urgency' => 'ปานกลาง', 'location' => 'อาคาร A ชั้น 4', 'comment' => 'กลับมาใช้งานได้ดี สัญญาณแรงขึ้น', 'improvement' => ''],
        ];

        $count = 0;
        foreach ($samples as $i => $s) {
            $when = now()->subDays(random_int(5, 170))->subHours(random_int(0, 23));
            $orgId = $orgUnitIds[array_rand($orgUnitIds)];
            $uId = $userIds[array_rand($userIds)];
            $refNo = sprintf('RPE-%s-%04d', $when->format('ym'), $i + 1);

            // 1. ใบแจ้งซ่อมที่ปิดงานแล้ว (approved) — ต้องมี instance approved + ฟอร์ม evaluation_enabled
            //    เพื่อให้ response-rate ของรายงานนับใบนี้ได้
            $inst = new ApprovalInstance([
                'workflow_id' => $repairWfId,
                'org_unit_id' => $orgId,
                'requester_user_id' => $uId,
                'document_type' => 'repair_request',
                'reference_no' => $refNo,
                'payload' => ['demo' => true],
                'current_step_no' => 1,
                'status' => 'approved',
            ]);
            $inst->created_at = $when;
            $inst->updated_at = $when;
            $inst->save();

            $parent = new DocumentFormSubmission([
                'form_id' => $repairForm->id,
                'user_id' => $uId,
                'org_unit_id' => $orgId,
                'approval_instance_id' => $inst->id,
                'reference_no' => $refNo,
                'status' => 'submitted',
                'payload' => [
                    'title' => $s['title'],
                    'detail' => $s['comment'],
                    'repair_category' => $s['category'],
                    'urgency' => $s['urgency'],
                    'location' => $s['location'],
                    'demo' => true,
                ],
            ]);
            $parent->created_at = $when;
            $parent->updated_at = $when;
            $parent->save();

            // 2. ใบประเมินผูกกลับ (ประเมินหลังปิดงาน 1-3 วัน)
            $evalWhen = $when->copy()->addDays(random_int(1, 3));
            $eval = new DocumentFormSubmission([
                'form_id' => $evalForm->id,
                'user_id' => $uId,
                'org_unit_id' => $orgId,
                'parent_submission_id' => $parent->id,
                'status' => 'submitted',
                'payload' => [
                    'overall_rating' => $s['rating'],
                    'comment' => $s['comment'],
                    'improvement' => $s['improvement'],
                ],
            ]);
            $eval->created_at = $evalWhen;
            $eval->updated_at = $evalWhen;
            $eval->save();

            $count++;
        }

        $this->command?->info("Seeded {$count} closed repair requests + evaluations for the evaluation report.");
    }
}
