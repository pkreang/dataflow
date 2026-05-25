<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\KpiCycle;
use App\Models\KpiCycleAssignment;
use App\Models\User;
use App\Services\Kpi\KpiCycleReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Aggregation behavior for KpiCycleReporter — given a cycle, return per-target,
 * per-role averages over numeric / formula field values from submitted forms.
 *
 * Counted statuses: `submitted` only. Drafts and any not-yet-finalized state
 * are excluded so a half-finished cycle doesn't inflate the report.
 */
class KpiCycleReporterTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_summarize_single_supervisor_submission(): void
    {
        $ctx = $this->makeCycleWithForm(['score_a', 'score_b']);
        $target = $this->makeRegularUser('t1-'.uniqid().'@x.test');
        $evaluator = $this->makeRegularUser('e1-'.uniqid().'@x.test');

        $this->makeSubmittedAssignment($ctx, $target, $evaluator, 'supervisor', [
            'score_a' => 4,
            'score_b' => 6,
        ]);

        $summary = app(KpiCycleReporter::class)->summarize($ctx['cycle']);

        $this->assertCount(1, $summary['targets']);
        $row = $summary['targets'][0];
        $this->assertSame((int) $target->id, (int) $row['user']->id);
        $this->assertSame(1, $row['supervisor']['completed']);
        $this->assertSame(1, $row['supervisor']['total']);
        $this->assertSame(5.0, $row['supervisor']['avg']); // (4+6)/2
        $this->assertSame(0, $row['self']['completed']);
        $this->assertNull($row['self']['avg']);
    }

    public function test_summarize_groups_self_and_supervisor_separately(): void
    {
        $ctx = $this->makeCycleWithForm(['score']);
        $target = $this->makeRegularUser('t2-'.uniqid().'@x.test');
        $supervisor = $this->makeRegularUser('s2-'.uniqid().'@x.test');

        $this->makeSubmittedAssignment($ctx, $target, $target, 'self', ['score' => 5]);
        $this->makeSubmittedAssignment($ctx, $target, $supervisor, 'supervisor', ['score' => 3]);

        $summary = app(KpiCycleReporter::class)->summarize($ctx['cycle']);
        $row = $summary['targets'][0];

        $this->assertSame(5.0, $row['self']['avg']);
        $this->assertSame(3.0, $row['supervisor']['avg']);
        $this->assertNull($row['peer']['avg']);
        $this->assertSame(4.0, $row['overall_avg']); // mean of non-null role avgs (5+3)/2
    }

    public function test_summarize_excludes_draft_submissions(): void
    {
        $ctx = $this->makeCycleWithForm(['score']);
        $target = $this->makeRegularUser('t3-'.uniqid().'@x.test');
        $evaluator = $this->makeRegularUser('e3-'.uniqid().'@x.test');

        $sub = DocumentFormSubmission::query()->create([
            'form_id' => $ctx['form']->id,
            'user_id' => $evaluator->id,
            'payload' => ['score' => 9],
            'status' => 'draft', // still in progress
        ]);
        KpiCycleAssignment::query()->create([
            'cycle_id' => $ctx['cycle']->id,
            'target_user_id' => $target->id,
            'evaluator_user_id' => $evaluator->id,
            'role' => 'supervisor',
            'submission_id' => $sub->id,
        ]);

        $summary = app(KpiCycleReporter::class)->summarize($ctx['cycle']);
        $row = $summary['targets'][0];

        $this->assertSame(0, $row['supervisor']['completed']);
        $this->assertSame(1, $row['supervisor']['total']);
        $this->assertNull($row['supervisor']['avg']);
    }

    public function test_summarize_ignores_non_numeric_fields(): void
    {
        // Form has a text field + a number field — only the number counts in avg.
        $form = DocumentForm::factory()->create();
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'note', 'label' => 'Note',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'score', 'label' => 'Score',
            'field_type' => 'number', 'sort_order' => 2, 'editable_by' => ['requester'],
        ]);
        $cycle = KpiCycle::query()->create([
            'name' => 'Mixed-field cycle', 'form_id' => $form->id, 'status' => 'open',
        ]);
        $target = $this->makeRegularUser('t4-'.uniqid().'@x.test');
        $evaluator = $this->makeRegularUser('e4-'.uniqid().'@x.test');

        $this->makeSubmittedAssignment(
            ['cycle' => $cycle, 'form' => $form->fresh('fields')],
            $target,
            $evaluator,
            'supervisor',
            ['note' => 'good work', 'score' => 8],
        );

        $summary = app(KpiCycleReporter::class)->summarize($cycle);
        $row = $summary['targets'][0];

        $this->assertSame(8.0, $row['supervisor']['avg']);
    }

    public function test_summarize_returns_empty_for_cycle_with_no_assignments(): void
    {
        $ctx = $this->makeCycleWithForm(['score']);

        $summary = app(KpiCycleReporter::class)->summarize($ctx['cycle']);

        $this->assertSame([], $summary['targets']);
    }

    public function test_summarize_includes_assignment_with_no_submission_as_total_not_completed(): void
    {
        $ctx = $this->makeCycleWithForm(['score']);
        $target = $this->makeRegularUser('t5-'.uniqid().'@x.test');
        $evaluator = $this->makeRegularUser('e5-'.uniqid().'@x.test');

        // Assignment exists but cycle never opened — no submission_id.
        KpiCycleAssignment::query()->create([
            'cycle_id' => $ctx['cycle']->id,
            'target_user_id' => $target->id,
            'evaluator_user_id' => $evaluator->id,
            'role' => 'supervisor',
        ]);

        $summary = app(KpiCycleReporter::class)->summarize($ctx['cycle']);
        $row = $summary['targets'][0];

        $this->assertSame(1, $row['supervisor']['total']);
        $this->assertSame(0, $row['supervisor']['completed']);
        $this->assertNull($row['supervisor']['avg']);
    }

    // ---- helpers ----

    /**
     * @return array{cycle: KpiCycle, form: DocumentForm}
     */
    private function makeCycleWithForm(array $numericFieldKeys): array
    {
        $form = DocumentForm::factory()->create();
        foreach ($numericFieldKeys as $i => $key) {
            DocumentFormField::query()->create([
                'form_id' => $form->id,
                'field_key' => $key,
                'label' => strtoupper($key),
                'field_type' => 'number',
                'sort_order' => $i + 1,
                'editable_by' => ['requester'],
            ]);
        }

        $cycle = KpiCycle::query()->create([
            'name' => 'Test cycle '.uniqid(),
            'form_id' => $form->id,
            'status' => 'open',
        ]);

        return ['cycle' => $cycle, 'form' => $form->fresh('fields')];
    }

    /**
     * Create an assignment + a submitted submission with the given payload.
     */
    private function makeSubmittedAssignment(array $ctx, User $target, User $evaluator, string $role, array $payload): KpiCycleAssignment
    {
        $submission = DocumentFormSubmission::query()->create([
            'form_id' => $ctx['form']->id,
            'user_id' => $evaluator->id,
            'payload' => $payload,
            'status' => 'submitted',
        ]);

        return KpiCycleAssignment::query()->create([
            'cycle_id' => $ctx['cycle']->id,
            'target_user_id' => $target->id,
            'evaluator_user_id' => $evaluator->id,
            'role' => $role,
            'submission_id' => $submission->id,
        ]);
    }
}
