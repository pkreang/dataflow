<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\Company;
use App\Models\Department;
use App\Models\OrgUnit;
use App\Models\OrgUnitWorkflowBinding;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Org-model consolidation Phase 2a — author org-native demo data บน demo ที่มีแค่
 * Company/Department/User (ยังไม่มี org_units). สร้าง org tree mirror จาก departments,
 * ตั้ง bridge (departments.org_unit_id), assign users.org_unit_id, ตั้ง head, แล้วผูก
 * org-routed workflow เพื่อพิสูจน์ว่า resolveWorkflowId อ่าน org path ได้บน data จริง.
 *
 * idempotent (firstOrCreate). transitional — Phase 3 vertical seeders จะเขียน org-native
 * เองทั้งหมดแล้วแทนที่ seeder นี้. ดู doc/org-model-consolidation-spec.md
 */
class OrgStructureDemoSeeder extends Seeder
{
    /** จัดอันดับความอาวุโสจากชื่อตำแหน่ง เพื่อเลือก head ของแต่ละ org unit. */
    private function seniority(?User $user): int
    {
        $name = $user?->jobPosition?->name ?? '';

        return match (true) {
            str_contains($name, 'กรรมการ') => 100,
            str_contains($name, 'ผู้อำนวยการ') => 90,
            str_contains($name, 'ผู้จัดการ') => 80,
            str_starts_with($name, 'หัวหน้า') => 70,
            str_contains($name, 'รองหัวหน้า') => 60,
            default => 10,
        };
    }

    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();

        // 1. root org unit = องค์กร/บริษัท
        $root = OrgUnit::firstOrCreate(
            ['name' => $company?->name ?? 'องค์กร', 'parent_id' => null],
            ['type' => 'company', 'is_active' => true],
        );

        // 2. mirror departments -> child org units (tree) + bridge + assign members + head
        foreach (Department::query()->orderBy('id')->get() as $i => $dept) {
            $org = OrgUnit::firstOrCreate(
                ['name' => $dept->name, 'parent_id' => $root->id],
                ['type' => 'department', 'is_active' => true, 'sort_order' => $i + 1],
            );

            $dept->update(['org_unit_id' => $org->id]);                          // bridge
            User::query()->where('department_id', $dept->id)
                ->update(['org_unit_id' => $org->id]);                           // assign members

            $head = User::query()->where('department_id', $dept->id)
                ->with('jobPosition')->get()
                ->sortByDesc(fn (User $u) => $this->seniority($u))->first();
            if ($head) {
                $org->update(['head_user_id' => $head->id]);
            }
        }

        // root head = ผู้บริหารอาวุโสสุดทั้งองค์กร (กรรมการผู้จัดการ)
        $rootHead = User::query()->whereNotNull('position_id')->with('jobPosition')->get()
            ->sortByDesc(fn (User $u) => $this->seniority($u))->first();
        if ($rootHead) {
            $root->update(['head_user_id' => $rootHead->id]);
        }

        // 3. org-routed workflow (แจ้งซ่อม) — stage 1 หัวหน้า org ของผู้ยื่น, stage 2 หัวหน้า org แม่
        $workflow = ApprovalWorkflow::firstOrCreate(
            ['name' => 'อนุมัติแจ้งซ่อม (org demo)', 'document_type' => 'repair_request'],
            ['is_active' => true, 'allow_requester_as_approver' => false],
        );
        if ($workflow->stages()->count() === 0) {
            ApprovalWorkflowStage::query()->create([
                'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'หัวหน้าฝ่าย',
                'approver_type' => 'org_head', 'approver_ref' => '',
                'min_approvals' => 1, 'is_active' => true,
            ]);
            ApprovalWorkflowStage::query()->create([
                'workflow_id' => $workflow->id, 'step_no' => 2, 'name' => 'ผู้บริหาร (org แม่)',
                'approver_type' => 'org_parent_head', 'approver_ref' => '',
                'min_approvals' => 1, 'is_active' => true,
            ]);
        }

        // ผูก org unit ทุกฝ่ายเข้ากับ workflow นี้ผ่าน org_unit binding (org path)
        foreach (OrgUnit::query()->where('parent_id', $root->id)->get() as $org) {
            OrgUnitWorkflowBinding::firstOrCreate(
                ['org_unit_id' => $org->id, 'document_type' => 'repair_request'],
                ['workflow_id' => $workflow->id],
            );
        }

        $this->command?->info(sprintf(
            'OrgStructureDemoSeeder: root #%d + %d depts mirrored, %d org bindings, workflow #%d',
            $root->id,
            OrgUnit::where('parent_id', $root->id)->count(),
            OrgUnitWorkflowBinding::where('document_type', 'repair_request')->count(),
            $workflow->id,
        ));
    }
}
