<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds demo data so the report dashboards have something to plot for the
 * pitch screenshots. Idempotent-ish: skips if there are already > 20 rows
 * in either source, to avoid blowing up over many runs.
 */
class DemoDashboardDataSeeder extends Seeder
{
    public function run(): void
    {
        $orgUnitIds = OrgUnit::where('type', 'department')->pluck('id')->all();
        $userIds = User::pluck('id')->all();
        if (empty($orgUnitIds) || empty($userIds)) {
            $this->command?->warn('Org units or users missing — seed those first.');

            return;
        }

        $this->seedRepairRequests($orgUnitIds, $userIds);
        $this->seedNteqMaintenance($orgUnitIds, $userIds);
    }

    private function seedRepairRequests(array $orgUnitIds, array $userIds): void
    {
        $existing = ApprovalInstance::where('document_type', 'repair_request')->count();
        if ($existing > 20) {
            $this->command?->info("Skipping repair_requests — already has {$existing} rows.");

            return;
        }

        $workflowId = ApprovalWorkflow::query()->orderBy('id')->value('id');
        if (! $workflowId) {
            $this->command?->warn('No approval workflow exists — cannot seed repair_requests.');

            return;
        }

        // status column is enum('pending','approved','rejected','cancelled')
        $statuses = [
            'pending' => 18,
            'approved' => 22,
            'rejected' => 5,
            'cancelled' => 3,
        ];

        $created = 0;
        foreach ($statuses as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $daysAgo = random_int(0, 180);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 23));
                ApprovalInstance::create([
                    'workflow_id' => $workflowId,
                    'org_unit_id' => $orgUnitIds[array_rand($orgUnitIds)],
                    'requester_user_id' => $userIds[array_rand($userIds)],
                    'document_type' => 'repair_request',
                    'reference_no' => sprintf('RR-%s-%04d', $createdAt->format('ym'), $created + 1),
                    'payload' => ['demo' => true],
                    'current_step_no' => $status === 'pending' ? 1 : ($status === 'approved' ? 3 : 2),
                    'status' => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(random_int(1, 48)),
                ]);
                $created++;
            }
        }
        $this->command?->info("Seeded {$created} repair_request approval instances.");
    }

    private function seedNteqMaintenance(array $orgUnitIds, array $userIds): void
    {
        $form = DocumentForm::where('form_key', 'nteq_maintenance')->first();
        if (! $form) {
            $this->command?->warn('Form nteq_maintenance not found — skipping fdata seed.');

            return;
        }
        $hasFdata = $form->hasDedicatedTable();
        $fdataTable = $hasFdata ? $form->submission_table : null;

        $existing = DocumentFormSubmission::where('form_id', $form->id)->count();
        if ($existing > 20) {
            $this->command?->info("Skipping nteq_maintenance — already has {$existing} submissions.");

            return;
        }

        // submission status enum is only ('draft','submitted') — for richer
        // chart segmentation, dashboards should group by priority/problem_type
        // (more cardinality) rather than status.
        $statuses = [
            'draft' => 8,
            'submitted' => 37,
        ];
        $priorities = ['low', 'normal', 'high', 'critical'];
        $problemTypes = ['mechanical', 'electrical', 'hydraulic', 'pneumatic', 'software', 'other'];

        $created = 0;
        foreach ($statuses as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $daysAgo = random_int(0, 180);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 23));
                $orgUnitId = $orgUnitIds[array_rand($orgUnitIds)];
                $userId = $userIds[array_rand($userIds)];
                $refNo = sprintf('NTEQ-%s-%04d', $createdAt->format('ym'), $created + 1);

                $payload = [
                    'reference_no' => $refNo,
                    'document_date' => $createdAt->toDateString(),
                    'priority' => $priorities[array_rand($priorities)],
                    'problem_type' => $problemTypes[array_rand($problemTypes)],
                    'description' => 'Demo submission for pitch screenshots.',
                ];

                $fdataRowId = null;
                if ($hasFdata && $fdataTable) {
                    $fdataRowId = DB::table($fdataTable)->insertGetId([
                        'user_id' => $userId,
                        'org_unit_id' => $orgUnitId,
                        'status' => $status,
                        'reference_no' => $refNo,
                        'document_date' => $createdAt->toDateString(),
                        'priority' => $payload['priority'],
                        'problem_type' => $payload['problem_type'],
                        'description' => $payload['description'],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                DocumentFormSubmission::create([
                    'form_id' => $form->id,
                    'user_id' => $userId,
                    'org_unit_id' => $orgUnitId,
                    'payload' => $payload,
                    'status' => $status,
                    'reference_no' => $refNo,
                    'fdata_row_id' => $fdataRowId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $created++;
            }
        }
        $this->command?->info("Seeded {$created} nteq_maintenance submissions".($hasFdata ? ' (with fdata)' : '').'.');
    }
}
