<?php

namespace App\Services\Kpi;

use App\Models\DocumentFormField;
use App\Models\KpiCycle;
use App\Models\KpiCycleAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Aggregate a KPI cycle into per-target × per-role averages — feeds the 360°
 * report view.
 *
 * Counted statuses: only DocumentFormSubmission.status='submitted'. Drafts and
 * cancelled rows are excluded so a half-finished cycle doesn't inflate scores.
 *
 * Numeric fields = field_type in {number, currency, formula}. For each
 * completed submission we collect every numeric value and average them
 * together (an "overall score" per submission), then average those across
 * submissions of the same role. The per-role avg is then averaged again to
 * yield a single `overall_avg` per target.
 */
class KpiCycleReporter
{
    /** @var list<string> */
    private const NUMERIC_TYPES = ['number', 'currency', 'formula'];

    /** @var list<string> */
    private const ROLES = ['self', 'supervisor', 'peer'];

    /**
     * @return array{cycle_id: int, targets: list<array{
     *     user: User|null,
     *     self: array{completed: int, total: int, avg: float|null},
     *     supervisor: array{completed: int, total: int, avg: float|null},
     *     peer: array{completed: int, total: int, avg: float|null},
     *     overall_avg: float|null,
     * }>}
     */
    public function summarize(KpiCycle $cycle): array
    {
        $cycle->loadMissing(['form.fields', 'assignments.target', 'assignments.submission']);

        $numericKeys = $cycle->form?->fields
            ->filter(fn (DocumentFormField $f) => in_array($f->field_type, self::NUMERIC_TYPES, true))
            ->pluck('field_key')
            ->all() ?? [];

        $targets = [];

        // Group assignments by target user.
        $byTarget = $cycle->assignments->groupBy('target_user_id');

        foreach ($byTarget as $targetId => $assignments) {
            $row = [
                'user' => $assignments->first()->target ?? null,
                'self' => $this->blankRoleStat(),
                'supervisor' => $this->blankRoleStat(),
                'peer' => $this->blankRoleStat(),
            ];

            foreach (self::ROLES as $role) {
                $roleAssignments = $assignments->filter(fn (KpiCycleAssignment $a) => $a->role === $role);
                $row[$role] = $this->aggregateRole($roleAssignments, $numericKeys);
            }

            $roleAvgs = collect([$row['self']['avg'], $row['supervisor']['avg'], $row['peer']['avg']])
                ->filter(fn ($v) => $v !== null);
            $row['overall_avg'] = $roleAvgs->isNotEmpty() ? $roleAvgs->avg() : null;

            $targets[] = $row;
        }

        return [
            'cycle_id' => (int) $cycle->id,
            'targets' => $targets,
        ];
    }

    /**
     * @param  Collection<int, KpiCycleAssignment>  $assignments
     * @param  list<string>  $numericKeys
     * @return array{completed: int, total: int, avg: float|null}
     */
    private function aggregateRole(Collection $assignments, array $numericKeys): array
    {
        $total = $assignments->count();
        $completed = 0;
        $perSubmissionAverages = [];

        foreach ($assignments as $a) {
            $submission = $a->submission;
            if (! $submission || $submission->status !== 'submitted') {
                continue;
            }
            $completed++;

            // payload is JSON-cast to array on the model. Local @var spells
            // it out for static analysis (the cast isn't surfaced on the
            // bare model property type) — the guard then handles real nulls.
            /** @var array<string, mixed>|null $rawPayload */
            $rawPayload = $submission->payload;
            $payload = is_array($rawPayload) ? $rawPayload : [];

            $values = collect($numericKeys)
                ->map(fn (string $key) => $payload[$key] ?? null)
                ->filter(fn ($v) => is_numeric($v));

            if ($values->isNotEmpty()) {
                $perSubmissionAverages[] = $values->map(fn ($v) => (float) $v)->avg();
            }
        }

        $avg = ! empty($perSubmissionAverages)
            ? collect($perSubmissionAverages)->avg()
            : null;

        return [
            'completed' => $completed,
            'total' => $total,
            'avg' => $avg !== null ? (float) $avg : null,
        ];
    }

    /**
     * @return array{completed: int, total: int, avg: float|null}
     */
    private function blankRoleStat(): array
    {
        return ['completed' => 0, 'total' => 0, 'avg' => null];
    }
}
