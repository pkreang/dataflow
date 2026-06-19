<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\PmPlan;
use App\Models\PmWorkOrder;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PmControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_plan_persists_with_task_items(): void
    {
        $this->seedBase();
        $eq = $this->makeEquipment();

        $response = $this->actingAsPlanner()->post(route('cmms.pm.plans.store'), [
            'equipment_id' => $eq->id,
            'name' => 'Monthly checkup',
            'description' => 'Routine inspection',
            'frequency_type' => 'date',
            'interval_days' => 30,
            'tasks' => [
                ['description' => 'Check oil level', 'task_type' => 'visual'],
                ['description' => 'Tighten bolts',   'task_type' => 'tightening', 'is_critical' => '1'],
            ],
        ]);

        $plan = PmPlan::where('name', 'Monthly checkup')->firstOrFail();
        $response->assertRedirect(route('cmms.pm.plans.edit', $plan));

        $this->assertSame(2, $plan->taskItems()->count());
        $this->assertSame('Tighten bolts', $plan->taskItems[1]->description);
        $this->assertTrue((bool) $plan->taskItems[1]->is_critical);
        // Step numbers are assigned sequentially from index
        $this->assertSame([1, 2], $plan->taskItems->pluck('step_no')->all());
    }

    public function test_update_plan_replaces_task_items(): void
    {
        $this->seedBase();
        $plan = $this->makePlan();
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'Old task', 'task_type' => 'visual']);

        $this->actingAsPlanner()->put(route('cmms.pm.plans.update', $plan), [
            'equipment_id' => $plan->equipment_id,
            'name' => 'Renamed',
            'frequency_type' => 'date',
            'interval_days' => 60,
            'tasks' => [
                ['description' => 'New task', 'task_type' => 'cleaning'],
            ],
        ])->assertRedirect();

        $plan->refresh()->load('taskItems');
        $this->assertSame('Renamed', $plan->name);
        $this->assertSame(60, $plan->interval_days);
        $this->assertSame(1, $plan->taskItems->count());
        $this->assertSame('New task', $plan->taskItems->first()->description);
    }

    public function test_destroy_plan_deletes_it(): void
    {
        $this->seedBase();
        $plan = $this->makePlan();

        $this->actingAsPlanner()->delete(route('cmms.pm.plans.destroy', $plan))->assertRedirect();

        $this->assertNull(PmPlan::find($plan->id));
    }

    public function test_generate_wo_now_creates_work_order(): void
    {
        $this->seedBase();
        $plan = $this->makePlan();
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        $this->actingAsPlanner()->post(route('cmms.pm.plans.generate-wo', $plan));

        $this->assertSame(1, PmWorkOrder::where('pm_plan_id', $plan->id)->count());
    }

    public function test_generate_wo_now_blocked_when_plan_inactive(): void
    {
        $this->seedBase();
        $plan = $this->makePlan(['is_active' => false]);
        $plan->taskItems()->create(['step_no' => 1, 'description' => 'X', 'task_type' => 'visual']);

        $this->actingAsPlanner()
            ->from(route('cmms.pm.plans.edit', $plan))
            ->post(route('cmms.pm.plans.generate-wo', $plan));

        $this->assertSame(0, PmWorkOrder::count());
    }

    public function test_start_work_order_transitions_to_in_progress(): void
    {
        $this->seedBase();
        $wo = $this->makeWorkOrder('due');

        $user = $this->makeExecutor();
        $this->actingAs($user)->post(route('cmms.pm.work-orders.start', $wo))->assertRedirect();

        $wo->refresh();
        $this->assertSame('in_progress', $wo->status);
        $this->assertNotNull($wo->started_at);
        $this->assertSame($user->id, $wo->assigned_to_user_id);
    }

    public function test_start_work_order_rejects_when_already_done(): void
    {
        $this->seedBase();
        $wo = $this->makeWorkOrder('done');

        $this->actingAs($this->makeExecutor())
            ->post(route('cmms.pm.work-orders.start', $wo))
            ->assertStatus(422);
    }

    public function test_complete_work_order_advances_plan_due_date(): void
    {
        $this->seedBase();
        $plan = $this->makePlan(['frequency_type' => 'date', 'interval_days' => 30, 'next_due_at' => now()->subDays(5)->toDateString()]);
        $taskItem = $plan->taskItems()->create(['step_no' => 1, 'description' => 'Inspect', 'task_type' => 'visual']);

        $wo = PmWorkOrder::create([
            'pm_plan_id' => $plan->id,
            'equipment_id' => $plan->equipment_id,
            'code' => 'WO-PM-COMPLETE',
            'status' => 'in_progress',
            'due_date' => now()->subDays(5)->toDateString(),
            'generated_at' => now(),
            'started_at' => now(),
        ]);
        $woItem = $wo->items()->create([
            'pm_task_item_id' => $taskItem->id,
            'step_no' => 1,
            'description' => 'Inspect',
            'task_type' => 'visual',
            'status' => 'pending',
        ]);

        $this->actingAs($this->makeExecutor())->post(route('cmms.pm.work-orders.complete', $wo), [
            'items' => [
                ['id' => $woItem->id, 'status' => 'done', 'note' => 'OK'],
            ],
            'findings' => 'all good',
        ]);

        $wo->refresh();
        $this->assertSame('done', $wo->status);
        $this->assertNotNull($wo->completed_at);
        $this->assertSame('all good', $wo->findings);

        $plan->refresh();
        $expected = now()->addDays(30)->toDateString();
        $this->assertSame($expected, $plan->next_due_at->toDateString());
    }

    public function test_cancel_work_order_transitions_to_cancelled(): void
    {
        $this->seedBase();
        $wo = $this->makeWorkOrder('due');

        $this->actingAsPlanner()->post(route('cmms.pm.work-orders.cancel', $wo))->assertRedirect();

        $this->assertSame('cancelled', $wo->fresh()->status);
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeEquipment(): Equipment
    {
        $cat = EquipmentCategory::create(['name' => 'Pump', 'code' => 'PUMP-'.uniqid(), 'is_active' => true]);
        $loc = EquipmentLocation::create(['name' => 'Plant', 'code' => 'P-'.uniqid(), 'is_active' => true]);
        return Equipment::create([
            'name' => 'Pump A',
            'code' => 'EQ-'.uniqid(),
            'equipment_category_id' => $cat->id,
            'equipment_location_id' => $loc->id,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function makePlan(array $overrides = []): PmPlan
    {
        $eq = $this->makeEquipment();
        return PmPlan::create(array_merge([
            'equipment_id' => $eq->id,
            'name' => 'Plan',
            'frequency_type' => 'date',
            'interval_days' => 30,
            'is_active' => true,
        ], $overrides));
    }

    private function makeWorkOrder(string $status): PmWorkOrder
    {
        $plan = $this->makePlan();
        return PmWorkOrder::create([
            'pm_plan_id' => $plan->id,
            'equipment_id' => $plan->equipment_id,
            'code' => 'WO-PM-'.uniqid(),
            'status' => $status,
            'due_date' => now()->toDateString(),
            'generated_at' => now(),
        ]);
    }

    private function actingAsPlanner(): self
    {
        $user = $this->makeUserWith(['pm.view', 'pm.plan']);
        return $this->actingAsWebSession($user);
    }

    private function makeExecutor(): User
    {
        return $this->makeUserWith(['pm.view', 'pm.execute']);
    }

    private function makeUserWith(array $perms): User
    {
        static $counter = 0;
        $counter++;
        $user = User::create([
            'first_name' => 'Tech',
            'last_name' => "User{$counter}",
            'email' => "tech{$counter}@test.local",
            'password' => bcrypt('x'),
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        foreach ($perms as $p) {
            $user->givePermissionTo(Permission::findByName($p));
        }
        return $user;
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-pm')->plainTextToken;
        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'is_super_admin' => false,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Override default actingAs to also seed the session structure that
     * AuthenticateWeb middleware expects (mirrors actingAsWebSession).
     */
    public function actingAs($user, $guard = null): self
    {
        return $this->actingAsWebSession($user);
    }
}
