<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EvaluationDemoSeeder extends Seeder
{
    /**
     * Optional demo: approve a few pending submissions and attach sample
     * evaluations so the /forms/evaluation_default/submissions list has rows.
     * Run explicitly: php artisan db:seed --class=EvaluationDemoSeeder
     */
    public function run(): void
    {
        $evalForm = DocumentForm::where('form_key', 'evaluation_default')->first();
        if (! $evalForm) {
            $this->command?->warn('evaluation_default form missing — run EvaluationFormSeeder first.');
            return;
        }

        // Pick 3 pending submissions (with approval instance) → approve them
        $pending = DocumentFormSubmission::query()
            ->whereHas('instance', fn ($q) => $q->where('status', 'pending'))
            ->whereNull('parent_submission_id')
            ->limit(3)
            ->get();

        if ($pending->isEmpty()) {
            $this->command?->warn('No pending submissions to approve — run PendingApprovalsDemoSeeder first.');
            return;
        }

        $samples = [
            ['rating' => '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม',     'comment' => 'ช่างมาตามนัด แก้ไขเรียบร้อย เครื่องกลับมาทำงานปกติ', 'improvement' => ''],
            ['rating' => '4 — ⭐⭐⭐⭐ พอใจมาก',       'comment' => 'งานเสร็จเรียบร้อย แต่ใช้เวลานานกว่าที่คาด',         'improvement' => 'อยากให้เร็วขึ้นถ้าทำได้'],
            ['rating' => '3 — ⭐⭐⭐ พอใจ',           'comment' => 'แก้ปัญหาเฉพาะหน้าได้ แต่ปัญหากลับมาอีก',             'improvement' => 'ควรตรวจสอบสาเหตุที่แท้จริงให้ละเอียด'],
        ];

        $count = 0;
        foreach ($pending as $idx => $submission) {
            $instance = $submission->instance;
            $instance->update([
                'status' => 'approved',
                'updated_at' => Carbon::now(),
            ]);

            // Skip if already evaluated
            if ($submission->evaluations()->exists()) {
                continue;
            }

            $sample = $samples[$idx % count($samples)];
            DocumentFormSubmission::create([
                'form_id' => $evalForm->id,
                'user_id' => $submission->user_id,
                'department_id' => $submission->department_id,
                'parent_submission_id' => $submission->id,
                'payload' => [
                    'overall_rating' => $sample['rating'],
                    'comment' => $sample['comment'],
                    'improvement' => $sample['improvement'],
                ],
                'status' => 'submitted',
                'created_at' => Carbon::now()->subHours(random_int(1, 24)),
                'updated_at' => Carbon::now(),
            ]);
            $count++;
        }

        $this->command?->info("Seeded {$count} evaluations + approved {$pending->count()} parent submissions.");
    }
}
