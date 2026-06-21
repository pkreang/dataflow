<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\PmPlan;
use App\Models\PmWorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GeneratePmWorkOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_work_order_for_due_plan(): void
    {
        $plan = $this->makeDuePlan();

        $this->artisan('pm:generate-work-orders')
            ->expectsOutputToContain('Generated 1 new PM work order')
            ->assertSuccessful();

        $this->assertSame(1, PmWorkOrder::where('pm_plan_id', $plan->id)->count());
    }

    public function test_command_flags_overdue_before_generating_new_ones(): void
    {
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => Carbon::today()->addMonth()]);
        // Existing 'due' WO with past due date — should be flagged
        $stale = PmWorkOrder::create([
            'pm_plan_id' => $plan->id, 'equipment_id' => $plan->equipment_id,
            'code' => 'WO-OLD', 'status' => 'due', 'due_date' => Carbon::yesterday(), 'generated_at' => now(),
        ]);

        $this->artisan('pm:generate-work-orders')
            ->expectsOutputToContain('Flagged 1 existing')
            ->assertSuccessful();

        $this->assertSame('overdue', $stale->fresh()->status);
    }

    public function test_dry_run_does_not_persist_new_work_orders(): void
    {
        $this->makeDuePlan();

        $this->artisan('pm:generate-work-orders', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertSame(0, PmWorkOrder::count());
    }

    private function makeDuePlan(): PmPlan
    {
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => Carbon::yesterday()]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        return $plan;
    }

    private function makePlan(array $overrides = []): PmPlan
    {
        $cat = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP-'.uniqid(), 'is_active' => true]);
        $loc = EquipmentLocation::create(['name' => 'Plant', 'code' => 'P-'.uniqid(), 'is_active' => true]);
        $eq = Equipment::create([
            'name' => 'Pump A', 'code' => 'EQ-'.uniqid(),
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
            'status' => 'active', 'is_active' => true,
        ]);

        return PmPlan::create(array_merge([
            'equipment_id' => $eq->id,
            'name' => 'Plan',
            'frequency_type' => 'date',
            'interval_days' => 30,
            'is_active' => true,
        ], $overrides));
    }
}
