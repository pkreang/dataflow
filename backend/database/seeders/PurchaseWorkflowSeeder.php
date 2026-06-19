<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentFormWorkflowRange;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PurchaseWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $deptMgr = Position::where('code', 'DEPT_MGR')->first();
        $plantMgr = Position::where('code', 'PLANT_MGR')->first();

        if (! $deptMgr || ! $plantMgr) {
            $this->command?->warn('PurchaseWorkflowSeeder: positions missing; run FactoryPositionSeeder first.');

            return;
        }

        $prSmall = $this->createWorkflow('PR - Small (≤50k)', 'purchase_request', 'ใบขอซื้อมูลค่าต่ำ', [
            ['step_no' => 1, 'name' => 'ผจก.แผนกอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $deptMgr->id],
        ]);

        $prLarge = $this->createWorkflow('PR - Large (>50k)', 'purchase_request', 'ใบขอซื้อมูลค่าสูง', [
            ['step_no' => 1, 'name' => 'ผจก.แผนกอนุมัติ',    'approver_type' => 'position', 'approver_ref' => $deptMgr->id],
            ['step_no' => 2, 'name' => 'ผจก.โรงงานอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $plantMgr->id],
        ]);

        $poStandard = $this->createWorkflow('PO - Standard', 'purchase_order', 'ใบสั่งซื้อทุกมูลค่า', [
            ['step_no' => 1, 'name' => 'ผจก.โรงงานอนุมัติ', 'approver_type' => 'position', 'approver_ref' => $plantMgr->id],
        ]);

        $this->createAmountPolicy('purchase_request_default', [
            ['min' => 0,        'max' => 50000, 'workflow' => $prSmall],
            ['min' => 50000.01, 'max' => null,  'workflow' => $prLarge],
        ]);

        $this->createAmountPolicy('purchase_order_default', [
            ['min' => 0, 'max' => null, 'workflow' => $poStandard],
        ]);

        $this->command?->info('PurchaseWorkflowSeeder: 3 workflows + 2 amount policies created.');
    }

    private function createWorkflow(string $name, string $documentType, string $description, array $stages): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['name' => $name],
            ['document_type' => $documentType, 'description' => $description, 'is_active' => true]
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
            $this->command?->warn("PurchaseWorkflowSeeder: form {$formKey} not found.");

            return;
        }

        $policy = DocumentFormWorkflowPolicy::updateOrCreate(
            ['form_id' => $form->id],
            ['use_amount_condition' => true, 'workflow_id' => $ranges[0]['workflow']->id]
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
