<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\KpiCycle;
use App\Models\KpiCycleAssignment;
use App\Models\User;
use App\Services\Kpi\KpiCycleOpener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Lifecycle coverage for KpiCycleOpener — open() spawns one draft submission
 * per assignment; close() locks the cycle. Both are transactional and reject
 * out-of-order transitions.
 */
class KpiCycleOpenerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_open_creates_one_draft_submission_per_assignment(): void
    {
        [$cycle, $assignments] = $this->makeCycleWithAssignments(2);

        app(KpiCycleOpener::class)->open($cycle);

        $this->assertSame(2, DocumentFormSubmission::query()->where('form_id', $cycle->form_id)->count());

        foreach ($assignments as $a) {
            $fresh = $a->fresh();
            $this->assertNotNull($fresh->submission_id);
            $sub = $fresh->submission;
            $this->assertSame((int) $a->evaluator_user_id, (int) $sub->user_id);
            $this->assertSame('draft', $sub->status);
        }
    }

    public function test_open_flips_cycle_status_and_stamps_opened_at(): void
    {
        [$cycle] = $this->makeCycleWithAssignments(1);

        $fresh = app(KpiCycleOpener::class)->open($cycle);

        $this->assertSame(KpiCycle::STATUS_OPEN, $fresh->status);
        $this->assertNotNull($fresh->opened_at);
    }

    public function test_open_rejects_already_open_cycle(): void
    {
        [$cycle] = $this->makeCycleWithAssignments(1);
        $cycle->update(['status' => KpiCycle::STATUS_OPEN]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cycle is not in draft');
        app(KpiCycleOpener::class)->open($cycle);
    }

    public function test_open_rejects_cycle_with_no_assignments(): void
    {
        $cycle = $this->makeBareCycle();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cycle has no assignments');
        app(KpiCycleOpener::class)->open($cycle);
    }

    public function test_close_flips_status_and_stamps_closed_at(): void
    {
        [$cycle] = $this->makeCycleWithAssignments(1);
        app(KpiCycleOpener::class)->open($cycle);

        $fresh = app(KpiCycleOpener::class)->close($cycle->fresh());

        $this->assertSame(KpiCycle::STATUS_CLOSED, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_close_rejects_non_open_cycle(): void
    {
        [$cycle] = $this->makeCycleWithAssignments(1);
        // still in draft

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cycle is not open');
        app(KpiCycleOpener::class)->close($cycle);
    }

    // ---- helpers ----

    /**
     * Build a draft cycle with $count assignments. Returns [cycle, assignments collection].
     *
     * @return array{0: KpiCycle, 1: \Illuminate\Database\Eloquent\Collection<int, KpiCycleAssignment>}
     */
    private function makeCycleWithAssignments(int $count): array
    {
        $cycle = $this->makeBareCycle();
        $assignments = collect();

        for ($i = 0; $i < $count; $i++) {
            $target = $this->makeRegularUser("kpi-target-{$i}-".uniqid().'@example.test');
            $evaluator = $this->makeRegularUser("kpi-evaluator-{$i}-".uniqid().'@example.test');
            $assignments->push(KpiCycleAssignment::query()->create([
                'cycle_id' => $cycle->id,
                'target_user_id' => $target->id,
                'evaluator_user_id' => $evaluator->id,
                'role' => KpiCycleAssignment::ROLE_SUPERVISOR,
            ]));
        }

        return [$cycle->fresh('assignments'), $assignments];
    }

    private function makeBareCycle(): KpiCycle
    {
        $form = DocumentForm::factory()->create();

        return KpiCycle::query()->create([
            'name' => 'Q2 2026 KPI',
            'form_id' => $form->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-06-30',
            'status' => KpiCycle::STATUS_DRAFT,
        ]);
    }
}
