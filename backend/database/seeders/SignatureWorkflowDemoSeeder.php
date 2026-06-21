<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SignatureWorkflowDemoSeeder extends Seeder
{
    /**
     * Optional demo: an approval form whose workflow requires a signature
     * at every stage (require_signature=true on all stages). Useful for
     * sales presentation — show that the system supports per-stage signatures.
     *
     * Run explicitly: php artisan db:seed --class=SignatureWorkflowDemoSeeder
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->where('is_super_admin', true)->first();

        if (! $admin) {
            $this->command?->warn('No admin user found — skip SignatureWorkflowDemo.');

            return;
        }

        // 1. Document type
        DocumentType::updateOrCreate(
            ['code' => 'sign_demo'],
            [
                'label_en' => 'Approval (Signature Demo)',
                'label_th' => 'ขออนุมัติ (เซ็นทุกขั้น)',
                'is_active' => true,
            ]
        );

        // 2. Form + fields
        $form = DocumentForm::updateOrCreate(
            ['form_key' => 'sign_demo_default'],
            [
                'name' => 'ใบขออนุมัติ (เซ็นทุกขั้น)',
                'document_type' => 'sign_demo',
                'description' => 'ตัวอย่างใบขออนุมัติที่ต้องเซ็นลายเซ็นในทุกขั้นของการอนุมัติ',
                'is_active' => true,
                'evaluation_enabled' => false,
                'layout_columns' => 1,
            ]
        );

        $fields = [
            ['field_key' => 'title',   'label' => 'หัวข้อขออนุมัติ', 'field_type' => 'text',     'is_required' => true,  'is_searchable' => true,  'sort_order' => 1],
            ['field_key' => 'detail',  'label' => 'รายละเอียด',     'field_type' => 'textarea', 'is_required' => false, 'is_searchable' => false, 'sort_order' => 2],
            ['field_key' => 'amount',  'label' => 'จำนวนเงิน (บาท)', 'field_type' => 'number',   'is_required' => false, 'is_searchable' => false, 'sort_order' => 3],
        ];
        foreach ($fields as $f) {
            DocumentFormField::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $f['field_key']],
                [
                    'label' => $f['label'],
                    'field_type' => $f['field_type'],
                    'is_required' => (bool) $f['is_required'],
                    'is_searchable' => (bool) $f['is_searchable'],
                    'sort_order' => $f['sort_order'],
                    'col_span' => 0,
                ]
            );
        }

        // 3. Workflow — 3 stages, ALL require_signature=true
        $wf = ApprovalWorkflow::updateOrCreate(
            ['name' => 'อนุมัติแบบเซ็นทุกขั้น'],
            [
                'document_type' => 'sign_demo',
                'description' => 'หัวหน้างาน → ผู้จัดการ → ผู้บริหาร (เซ็นทุกขั้น)',
                'is_active' => true,
            ]
        );
        $wf->stages()->delete();
        $stages = [
            ['step_no' => 1, 'name' => 'หัวหน้างาน'],
            ['step_no' => 2, 'name' => 'ผู้จัดการ'],
            ['step_no' => 3, 'name' => 'ผู้บริหาร'],
        ];
        foreach ($stages as $s) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $wf->id,
                'step_no' => $s['step_no'],
                'name' => $s['name'],
                'approver_type' => 'user',
                'approver_ref' => (string) $admin->id,
                'min_approvals' => 1,
                'require_signature' => true,
                'is_active' => true,
            ]);
        }

        // 4. Form → workflow binding (no amount condition)
        DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            ['use_amount_condition' => false, 'workflow_id' => $wf->id]
        );

        // 5. Seed 2 demo pending submissions so /approvals/my has rows immediately
        $requester = User::query()->where('id', '!=', $admin->id)->first() ?? $admin;
        $samples = [
            ['title' => 'อนุมัติซื้ออุปกรณ์สำนักงาน',  'detail' => 'เครื่องปริ้นเตอร์ใหม่ทดแทนเครื่องเก่าที่ชำรุด', 'amount' => 35000],
            ['title' => 'อนุมัติเช่ารถยนต์ดูงาน',      'detail' => 'จัดทริปฝึกอบรมทีมงาน 5 คน 3 วัน',           'amount' => 18500],
        ];
        $created = 0;
        foreach ($samples as $idx => $sample) {
            // Idempotency: skip if reference_no already exists for this seed batch
            $refNo = sprintf('SIGN-%s-%04d', Carbon::now()->format('ym'), 9100 + $idx);
            if (ApprovalInstance::where('reference_no', $refNo)->exists()) {
                continue;
            }
            $createdAt = Carbon::now()->subHours(random_int(1, 24));

            $submission = DocumentFormSubmission::create([
                'form_id' => $form->id,
                'user_id' => $requester->id,
                'org_unit_id' => $requester->org_unit_id,
                'payload' => $sample,
                'status' => 'submitted',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $instance = ApprovalInstance::create([
                'workflow_id' => $wf->id,
                'org_unit_id' => $requester->org_unit_id,
                'requester_user_id' => $requester->id,
                'document_type' => 'sign_demo',
                'reference_no' => $refNo,
                'payload' => $sample,
                'current_step_no' => 1,
                'status' => 'pending',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($stages as $s) {
                ApprovalInstanceStep::create([
                    'approval_instance_id' => $instance->id,
                    'step_no' => $s['step_no'],
                    'stage_name' => $s['name'],
                    'approver_type' => 'user',
                    'approver_ref' => (string) $admin->id,
                    'require_signature' => true,
                    'action' => 'pending',
                ]);
            }

            $submission->update(['approval_instance_id' => $instance->id, 'reference_no' => $refNo]);
            $created++;
        }

        $this->command?->info("Seeded sign_demo form + workflow + {$created} pending submissions (signature required on every stage).");
    }
}
