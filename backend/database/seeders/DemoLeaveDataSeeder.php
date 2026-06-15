<?php

namespace Database\Seeders;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoLeaveDataSeeder extends Seeder
{
    public function run(): void
    {
        $form = DocumentForm::where('form_key', 'leave_request_default')->first();
        if (! $form) {
            $this->command?->warn('leave_request_default form not found.');
            return;
        }

        $existing = DocumentFormSubmission::where('form_id', $form->id)->count();
        if ($existing > 20) {
            $this->command?->info("Skipping leave submissions — already has {$existing} rows.");
            return;
        }

        $userIds       = User::pluck('id')->all();
        $departmentIds = Department::pluck('id')->all();
        $workflowId    = ApprovalWorkflow::orderBy('id')->value('id');

        if (empty($userIds) || empty($departmentIds)) {
            $this->command?->warn('Users or departments missing.');
            return;
        }

        $leaveTypes = ['sick', 'personal', 'vacation', 'maternity', 'ordination', 'other'];

        $batches = [
            ['status' => 'draft',     'instanceStatus' => null,       'count' => 5],
            ['status' => 'submitted', 'instanceStatus' => 'pending',  'count' => 15],
            ['status' => 'submitted', 'instanceStatus' => 'approved', 'count' => 15],
            ['status' => 'submitted', 'instanceStatus' => 'rejected', 'count' => 5],
        ];

        $seq = DocumentFormSubmission::withTrashed()->max('id') ?? 0;

        foreach ($batches as $batch) {
            for ($i = 0; $i < $batch['count']; $i++) {
                $seq++;
                $daysAgo   = random_int(0, 90);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours(random_int(0, 23));
                $userId    = $userIds[array_rand($userIds)];
                $deptId    = $departmentIds[array_rand($departmentIds)];
                $leaveType = $leaveTypes[array_rand($leaveTypes)];
                $dateFrom  = Carbon::now()->addDays(random_int(1, 30))->format('Y-m-d');
                $dateTo    = Carbon::parse($dateFrom)->addDays(random_int(0, 4))->format('Y-m-d');
                $totalDays = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1;
                $refNo     = 'LV' . $createdAt->format('ym') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                $instanceId = null;
                if ($batch['instanceStatus'] && $workflowId) {
                    $instance = ApprovalInstance::create([
                        'workflow_id'       => $workflowId,
                        'department_id'     => $deptId,
                        'requester_user_id' => $userId,
                        'document_type'     => 'leave_request',
                        'reference_no'      => $refNo,
                        'payload'           => [],
                        'current_step_no'   => 1,
                        'status'            => $batch['instanceStatus'],
                        'created_at'        => $createdAt,
                        'updated_at'        => $createdAt->copy()->addHours(random_int(1, 24)),
                    ]);
                    $instanceId = $instance->id;
                }

                DocumentFormSubmission::create([
                    'form_id'               => $form->id,
                    'user_id'               => $userId,
                    'department_id'         => $deptId,
                    'status'                => $batch['status'],
                    'reference_no'          => $refNo,
                    'approval_instance_id'  => $instanceId,
                    'payload'               => [
                        'leave_type' => $leaveType,
                        'date_from'  => $dateFrom,
                        'date_to'    => $dateTo,
                        'total_days' => $totalDays,
                        'reason'     => null,
                    ],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(random_int(1, 30)),
                ]);
            }
        }

        $total = array_sum(array_column($batches, 'count'));
        $this->command?->info("Seeded {$total} leave_request_default submissions.");
    }
}
