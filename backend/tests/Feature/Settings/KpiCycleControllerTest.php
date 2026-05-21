<?php

namespace Tests\Feature\Settings;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\KpiCycle;
use App\Models\KpiCycleAssignment;
use App\Services\Kpi\KpiCycleOpener;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * KPI Cycle admin flow — CRUD + open/close lifecycle. The opener service is
 * covered separately (KpiCycleOpenerTest); this file exercises the HTTP
 * endpoints + assignment sync semantics.
 */
class KpiCycleControllerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_cycle(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = DocumentForm::factory()->create();

        $response = $this->actingAsWebSession($admin)
            ->post(route('settings.kpi-cycles.store'), [
                'name' => 'Q2 2026 KPI',
                'form_id' => $form->id,
                'period_start' => '2026-04-01',
                'period_end' => '2026-06-30',
            ]);

        $cycle = KpiCycle::query()->where('name', 'Q2 2026 KPI')->firstOrFail();
        $response->assertRedirect(route('settings.kpi-cycles.edit', $cycle));
        $this->assertSame('draft', $cycle->status);
    }

    public function test_update_replaces_assignments_atomically(): void
    {
        $admin = $this->makeSuperAdmin();
        $cycle = $this->makeCycle();
        $target = $this->makeRegularUser('kpi-t-'.uniqid().'@example.test');
        $evaluator = $this->makeRegularUser('kpi-e-'.uniqid().'@example.test');

        // Seed an existing assignment that should be removed
        $stale = KpiCycleAssignment::query()->create([
            'cycle_id' => $cycle->id,
            'target_user_id' => $target->id,
            'evaluator_user_id' => $evaluator->id,
            'role' => 'peer',
        ]);

        $this->actingAsWebSession($admin)
            ->put(route('settings.kpi-cycles.update', $cycle), [
                'name' => $cycle->name,
                'form_id' => $cycle->form_id,
                'period_start' => '2026-04-01',
                'period_end' => '2026-06-30',
                'assignments' => [
                    // Single new assignment — different role; old `peer` row should be removed
                    ['target_user_id' => $target->id, 'evaluator_user_id' => $evaluator->id, 'role' => 'supervisor'],
                ],
            ])->assertRedirect();

        $cycle->refresh()->load('assignments');
        $this->assertSame(1, $cycle->assignments->count());
        $this->assertSame('supervisor', $cycle->assignments->first()->role);
        $this->assertDatabaseMissing('kpi_cycle_assignments', ['id' => $stale->id]);
    }

    public function test_super_admin_can_open_cycle_creating_drafts(): void
    {
        $admin = $this->makeSuperAdmin();
        $cycle = $this->makeCycleWithAssignment();

        $response = $this->actingAsWebSession($admin)
            ->post(route('settings.kpi-cycles.open', $cycle));

        $response->assertRedirect();
        $this->assertSame('open', $cycle->fresh()->status);
        $this->assertSame(1, DocumentFormSubmission::query()->where('form_id', $cycle->form_id)->count());
    }

    public function test_super_admin_can_close_open_cycle(): void
    {
        $admin = $this->makeSuperAdmin();
        $cycle = $this->makeCycleWithAssignment();
        app(KpiCycleOpener::class)->open($cycle);

        $this->actingAsWebSession($admin)
            ->post(route('settings.kpi-cycles.close', $cycle))
            ->assertRedirect();

        $this->assertSame('closed', $cycle->fresh()->status);
    }

    public function test_cannot_destroy_non_draft_cycle(): void
    {
        $admin = $this->makeSuperAdmin();
        $cycle = $this->makeCycleWithAssignment();
        app(KpiCycleOpener::class)->open($cycle);

        $this->actingAsWebSession($admin)
            ->delete(route('settings.kpi-cycles.destroy', $cycle));

        // Still exists — non-draft cycles are protected
        $this->assertTrue(KpiCycle::query()->whereKey($cycle->id)->exists());
    }

    // ---- helpers ----

    private function makeCycle(): KpiCycle
    {
        return KpiCycle::query()->create([
            'name' => 'Test cycle '.uniqid(),
            'form_id' => DocumentForm::factory()->create()->id,
            'status' => 'draft',
        ]);
    }

    private function makeCycleWithAssignment(): KpiCycle
    {
        $cycle = $this->makeCycle();
        $target = $this->makeRegularUser('kpi-target-'.uniqid().'@example.test');
        $evaluator = $this->makeRegularUser('kpi-evaluator-'.uniqid().'@example.test');
        KpiCycleAssignment::query()->create([
            'cycle_id' => $cycle->id,
            'target_user_id' => $target->id,
            'evaluator_user_id' => $evaluator->id,
            'role' => 'supervisor',
        ]);

        return $cycle->fresh();
    }
}
