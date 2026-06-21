<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\PmPlan;
use App\Models\PmWorkOrder;
use App\Services\Cmms\PmWorkOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PmWorkOrderGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_snapshots_task_items_into_work_order_items(): void
    {
        $plan = $this->makePlan();
        $plan->taskItems()->createMany([
            ['step_no' => 1, 'sort_order' => 1, 'description' => 'Inspect bearings', 'task_type' => 'visual', 'is_critical' => true, 'requires_photo' => true],
            ['step_no' => 2, 'sort_order' => 2, 'description' => 'Measure pressure', 'task_type' => 'measurement', 'expected_value' => '50-80', 'unit' => 'bar'],
        ]);

        $wo = app(PmWorkOrderGenerator::class)->generate($plan);

        $this->assertSame('due', $wo->status);
        $this->assertSame($plan->id, $wo->pm_plan_id);
        $this->assertSame($plan->equipment_id, $wo->equipment_id);
        $this->assertCount(2, $wo->items);

        $itemOne = $wo->items->firstWhere('step_no', 1);
        $this->assertSame('Inspect bearings', $itemOne->description);
        $this->assertTrue($itemOne->is_critical);
        $this->assertTrue($itemOne->requires_photo);
        $this->assertSame('pending', $itemOne->status);

        $itemTwo = $wo->items->firstWhere('step_no', 2);
        $this->assertSame('50-80', $itemTwo->expected_value);
        $this->assertSame('bar', $itemTwo->unit);
    }

    public function test_generated_work_order_code_follows_wo_pm_yyyymm_seq_format(): void
    {
        Carbon::setTestNow('2026-04-28 10:00:00');
        $plan = $this->makePlan();
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'Step', 'task_type' => 'visual']);

        $first = app(PmWorkOrderGenerator::class)->generate($plan);
        $second = app(PmWorkOrderGenerator::class)->generate($plan);

        $this->assertSame('WO-PM-202604-00001', $first->code);
        $this->assertSame('WO-PM-202604-00002', $second->code);

        Carbon::setTestNow();
    }

    public function test_generate_due_now_skips_inactive_plans(): void
    {
        $plan = $this->makePlan(['is_active' => false, 'frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => Carbon::yesterday()]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        $count = app(PmWorkOrderGenerator::class)->generateDueNow();

        $this->assertSame(0, $count);
        $this->assertSame(0, PmWorkOrder::count());
    }

    public function test_generate_due_now_skips_plans_with_open_work_order(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => Carbon::yesterday()]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        // Pre-existing in_progress WO blocks regeneration
        PmWorkOrder::create([
            'pm_plan_id' => $plan->id, 'equipment_id' => $plan->equipment_id,
            'code' => 'WO-EXISTING', 'status' => 'in_progress', 'due_date' => Carbon::yesterday(),
            'generated_at' => now(),
        ]);

        $count = app(PmWorkOrderGenerator::class)->generateDueNow();

        $this->assertSame(0, $count);
        $this->assertSame(1, PmWorkOrder::count());
    }

    public function test_generate_due_now_creates_for_first_run_date_plan_with_null_next_due(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => null]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        $count = app(PmWorkOrderGenerator::class)->generateDueNow();

        $this->assertSame(1, $count);
    }

    public function test_generate_due_now_skips_runtime_plan_with_null_next_due_runtime(): void
    {
        // Runtime plans need explicit first-run setup — null next_due_runtime stays untouched
        $plan = $this->makePlan(['frequency_type' => 'runtime', 'interval_hours' => 500, 'next_due_runtime' => null]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        $count = app(PmWorkOrderGenerator::class)->generateDueNow();

        $this->assertSame(0, $count);
    }

    public function test_generate_due_now_creates_for_runtime_plan_when_equipment_runtime_reaches_threshold(): void
    {
        $plan = $this->makePlan([
            'frequency_type' => 'runtime', 'interval_hours' => 500, 'next_due_runtime' => 1000,
        ]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);
        $plan->equipment->update(['runtime_hours' => 1050]);

        $count = app(PmWorkOrderGenerator::class)->generateDueNow();

        $this->assertSame(1, $count);
    }

    public function test_flag_overdue_marks_due_work_orders_with_past_due_date(): void
    {
        $plan = $this->makePlan();

        $past = PmWorkOrder::create(['pm_plan_id' => $plan->id, 'equipment_id' => $plan->equipment_id, 'code' => 'WO-1', 'status' => 'due', 'due_date' => Carbon::yesterday(), 'generated_at' => now()]);
        $today = PmWorkOrder::create(['pm_plan_id' => $plan->id, 'equipment_id' => $plan->equipment_id, 'code' => 'WO-2', 'status' => 'due', 'due_date' => Carbon::today(), 'generated_at' => now()]);
        $inProgress = PmWorkOrder::create(['pm_plan_id' => $plan->id, 'equipment_id' => $plan->equipment_id, 'code' => 'WO-3', 'status' => 'in_progress', 'due_date' => Carbon::yesterday(), 'generated_at' => now()]);

        $flagged = app(PmWorkOrderGenerator::class)->flagOverdue();

        $this->assertSame(1, $flagged);
        $this->assertSame('overdue', $past->fresh()->status);
        $this->assertSame('due', $today->fresh()->status);
        $this->assertSame('in_progress', $inProgress->fresh()->status, 'in_progress is not eligible for overdue flagging');
    }

    public function test_advance_plan_after_completion_for_date_plan(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30]);
        $completedAt = Carbon::parse('2026-05-01 12:00:00');

        app(PmWorkOrderGenerator::class)->advancePlanAfterCompletion($plan, $completedAt, null);

        $plan->refresh();
        $this->assertSame('2026-05-31', $plan->next_due_at->toDateString());
        $this->assertSame($completedAt->timestamp, $plan->last_executed_at->timestamp);
    }

    public function test_advance_plan_after_completion_for_runtime_plan(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'runtime', 'interval_hours' => 500]);
        $completedAt = Carbon::parse('2026-05-01 12:00:00');

        app(PmWorkOrderGenerator::class)->advancePlanAfterCompletion($plan, $completedAt, 1200.5);

        $plan->refresh();
        $this->assertSame('1700.50', (string) $plan->next_due_runtime);
        $this->assertSame('1200.50', (string) $plan->last_executed_runtime);
    }

    public function test_advance_plan_skips_runtime_advance_when_no_runtime_reported(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'runtime', 'interval_hours' => 500, 'next_due_runtime' => 1000]);

        app(PmWorkOrderGenerator::class)->advancePlanAfterCompletion($plan, now(), null);

        // Without a current runtime reading we can't extrapolate the next threshold —
        // leave next_due_runtime untouched rather than guess.
        $this->assertSame('1000.00', (string) $plan->fresh()->next_due_runtime);
    }

    private function makePlan(array $overrides = []): PmPlan
    {
        $cat = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP', 'is_active' => true]);
        $loc = EquipmentLocation::create(['name' => 'Plant 1', 'code' => 'P1', 'is_active' => true]);
        $eq = Equipment::create([
            'name' => 'Pump A',
            'code' => 'EQ-PUMP-001',
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        return PmPlan::create(array_merge([
            'equipment_id' => $eq->id,
            'name' => 'Monthly inspection',
            'frequency_type' => 'date',
            'interval_days' => 30,
            'is_active' => true,
        ], $overrides));
    }
}
