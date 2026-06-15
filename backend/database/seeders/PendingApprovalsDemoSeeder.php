<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PendingApprovalsDemoSeeder extends Seeder
{
    /**
     * Optional demo: pending approvals targeting admin@example.com (or first super-admin).
     * Run explicitly: php artisan db:seed --class=PendingApprovalsDemoSeeder
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->where('is_super_admin', true)->first();

        if (! $admin) {
            $this->command?->warn('No admin user found — skip.');
            return;
        }

        // Use any other user as the requester so admin (current viewer) is not the requester
        $requester = User::query()->where('id', '!=', $admin->id)->first() ?? $admin;
        $deptId = $admin->department_id ?? \App\Models\Department::value('id');
        $workflowId = ApprovalWorkflow::query()->orderBy('id')->value('id');
        $form = DocumentForm::query()->where('form_key', 'repair_request_default')->first()
            ?? DocumentForm::query()->where('is_active', true)->first();

        if (! $form || ! $workflowId) {
            $this->command?->warn('Missing form or workflow — skip.');
            return;
        }

        $samples = [
            ['title' => 'มอเตอร์ดับ Line 3', 'location' => 'โรงผลิต A', 'detail' => 'มอเตอร์หยุดทำงานกะทันหัน เสียงผิดปกติก่อนหน้านี้'],
            ['title' => 'ระบบทำความเย็นรั่ว', 'location' => 'ห้องเครื่อง B2', 'detail' => 'พบน้ำหยดบริเวณคอมเพรสเซอร์'],
            ['title' => 'สายพานลำเลียงสะดุด', 'location' => 'Line 5', 'detail' => 'สายพานหลวม ต้องปรับความตึงด่วน'],
            ['title' => 'ไฟส่องสว่างชั้นโกดัง', 'location' => 'โกดังสินค้าสำเร็จรูป', 'detail' => 'หลอดดับ 3 จุด'],
            ['title' => 'ปั๊มน้ำชั้น 2 รั่ว', 'location' => 'ห้องน้ำสำนักงาน', 'detail' => 'น้ำขังบริเวณท่อ ต้องเปลี่ยน gasket'],
        ];

        $created = 0;
        foreach ($samples as $idx => $sample) {
            $createdAt = Carbon::now()->subHours(random_int(1, 72));

            $submission = DocumentFormSubmission::create([
                'form_id' => $form->id,
                'user_id' => $requester->id,
                'department_id' => $deptId,
                'payload' => $sample,
                'status' => 'submitted',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $refNo = sprintf('REQ-%s-%04d', $createdAt->format('ym'), 9000 + $idx);

            $instance = ApprovalInstance::create([
                'workflow_id' => $workflowId,
                'department_id' => $deptId,
                'requester_user_id' => $requester->id,
                'document_type' => 'repair_request',
                'reference_no' => $refNo,
                'payload' => $sample,
                'current_step_no' => 1,
                'status' => 'pending',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // 2-step pending workflow — admin is the step 1 approver (current step)
            ApprovalInstanceStep::create([
                'approval_instance_id' => $instance->id,
                'step_no' => 1,
                'stage_name' => 'หัวหน้างาน',
                'approver_type' => 'user',
                'approver_ref' => (string) $admin->id,
                'action' => 'pending',
            ]);
            ApprovalInstanceStep::create([
                'approval_instance_id' => $instance->id,
                'step_no' => 2,
                'stage_name' => 'ฝ่ายบำรุงรักษา',
                'approver_type' => 'user',
                'approver_ref' => (string) $admin->id,
                'action' => 'pending',
            ]);

            $submission->update(['approval_instance_id' => $instance->id, 'reference_no' => $refNo]);
            $created++;
        }

        $this->command?->info("Seeded {$created} pending approvals waiting on user #{$admin->id} ({$admin->email}).");
    }
}
