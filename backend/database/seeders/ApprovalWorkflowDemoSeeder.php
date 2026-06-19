<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentFormWorkflowRange;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Seeds position-based approval workflows for spare_parts_requisition.
 * Amount-based routing with realistic Thai factory thresholds.
 * Requires FactoryPositionSeeder (MAINT_SUP, DEPT_MGR, PLANT_MGR). Does not seed demo users.
 */
class ApprovalWorkflowDemoSeeder extends Seeder
{
    public function run(): void
    {
        $maintSup = Position::where('code', 'MAINT_SUP')->first();
        $deptMgr = Position::where('code', 'DEPT_MGR')->first();
        $plantMgr = Position::where('code', 'PLANT_MGR')->first();

        if (! $maintSup || ! $deptMgr || ! $plantMgr) {
            $this->command?->warn('ApprovalWorkflowDemoSeeder: positions missing; run FactoryPositionSeeder first.');

            return;
        }

        // ─── Spare Parts Requisition Workflows ─────────────────

        $spSmall = $this->createWorkflow('Spare Parts - Low (<5k)', 'spare_parts_requisition', 'เบิกอะไหล่มูลค่าต่ำ', [
            ['step_no' => 1, 'name' => 'หัวหน้าช่างอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $maintSup->id],
        ]);

        $spMedium = $this->createWorkflow('Spare Parts - Medium (5k-50k)', 'spare_parts_requisition', 'เบิกอะไหล่มูลค่าปานกลาง', [
            ['step_no' => 1, 'name' => 'หัวหน้าช่างอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $maintSup->id],
            ['step_no' => 2, 'name' => 'ผจก.แผนกอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $deptMgr->id],
        ]);

        $spLarge = $this->createWorkflow('Spare Parts - High (>50k)', 'spare_parts_requisition', 'เบิกอะไหล่มูลค่าสูง', [
            ['step_no' => 1, 'name' => 'หัวหน้าช่างอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $maintSup->id],
            ['step_no' => 2, 'name' => 'ผจก.แผนกอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $deptMgr->id],
            ['step_no' => 3, 'name' => 'ผจก.โรงงานอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $plantMgr->id],
        ]);

        // ─── Document Form Workflow Policies (amount-based) ────

        $this->createAmountPolicy('spare_parts_requisition_default', [
            ['min' => 0, 'max' => 4999.99, 'workflow' => $spSmall],
            ['min' => 5000, 'max' => 50000, 'workflow' => $spMedium],
            ['min' => 50000.01, 'max' => null, 'workflow' => $spLarge],
        ]);

        $this->command?->info('ApprovalWorkflowDemoSeeder: 3 spare-parts workflows + 1 amount-based policy (no demo users; use admin@example.com only).');
    }

    private function createWorkflow(string $name, string $documentType, string $description, array $stages): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['name' => $name],
            [
                'document_type' => $documentType,
                'description' => $description,
                'is_active' => true,
            ]
        );

        $workflow->stages()->delete();

        foreach ($stages as $stage) {
            ApprovalWorkflowStage::create([
                'workflow_id' => $workflow->id,
                'step_no' => $stage['step_no'],
                'name' => $stage['name'],
                'approver_type' => $stage['approver_type'],
                'approver_ref' => (string) $stage['approver_ref'],
                'min_approvals' => 1,
                'is_active' => true,
            ]);
        }

        return $workflow;
    }

    private function createAmountPolicy(string $formKey, array $ranges): void
    {
        $form = DocumentForm::where('form_key', $formKey)->first();
        if (! $form) {
            $this->command?->warn("ApprovalWorkflowDemoSeeder: form {$formKey} not found; skipping policy.");

            return;
        }

        $policy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            [
                'use_amount_condition' => true,
                'workflow_id' => $ranges[0]['workflow']->id, // fallback
            ]
        );

        $policy->ranges()->delete();

        foreach ($ranges as $i => $range) {
            DocumentFormWorkflowRange::create([
                'policy_id' => $policy->id,
                'min_amount' => $range['min'],
                'max_amount' => $range['max'],
                'workflow_id' => $range['workflow']->id,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
