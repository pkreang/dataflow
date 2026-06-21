<?php

namespace App\Services\Cmms;

use App\Models\PmPlan;
use App\Models\PmWorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PmWorkOrderGenerator
{
    /**
     * Create a PM work order from a plan (snapshot task items).
     * Returns the generated WO. Does NOT advance next_due_* — that happens on WO completion.
     */
    public function generate(PmPlan $plan, ?Carbon $dueDate = null): PmWorkOrder
    {
        $plan->loadMissing('taskItems');

        return DB::transaction(function () use ($plan, $dueDate) {
            $wo = PmWorkOrder::create([
                'pm_plan_id' => $plan->id,
                'equipment_id' => $plan->equipment_id,
                'code' => $this->nextCode(),
                'status' => 'due',
                'due_date' => ($dueDate ?? $this->dueDateFor($plan))->toDateString(),
                'generated_at' => now(),
            ]);

            foreach ($plan->taskItems as $item) {
                $wo->items()->create([
                    'pm_task_item_id' => $item->id,
                    'step_no' => $item->step_no,
                    'description' => $item->description,
                    'task_type' => $item->task_type,
                    'expected_value' => $item->expected_value,
                    'unit' => $item->unit,
                    'requires_photo' => $item->requires_photo,
                    'requires_signature' => $item->requires_signature,
                    'spare_part_id' => $item->spare_part_id,
                    'estimated_minutes' => $item->estimated_minutes,
                    'loto_required' => $item->loto_required,
                    'is_critical' => $item->is_critical,
                    'status' => 'pending',
                ]);
            }

            return $wo;
        });
    }

    /**
     * Find active plans that are due and have no open WO, and generate WOs for them.
     * Returns count generated.
     *
     * Rules:
     *  - date-based: next_due_at <= today OR null (first run)
     *  - runtime-based: equipment.runtime_hours >= next_due_runtime (if set)
     *  - skip if the plan already has an open WO (status in due/in_progress/overdue)
     */
    public function generateDueNow(): int
    {
        $today = now()->startOfDay();
        $generated = 0;

        $candidates = PmPlan::query()
            ->where('is_active', true)
            ->with(['equipment', 'taskItems'])
            ->get();

        foreach ($candidates as $plan) {
            if (! $this->isDue($plan, $today)) {
                continue;
            }
            if ($this->hasOpenWo($plan)) {
                continue;
            }
            $this->generate($plan);
            $generated++;
        }

        return $generated;
    }

    /**
     * Mark 'due' WOs whose due_date has passed as 'overdue'.
     */
    public function flagOverdue(): int
    {
        return PmWorkOrder::where('status', 'due')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    /**
     * Called after a WO is completed — advance the plan's next due markers.
     */
    public function advancePlanAfterCompletion(PmPlan $plan, Carbon $completedAt, ?float $equipmentRuntimeAtCompletion): void
    {
        $payload = [
            'last_executed_at' => $completedAt,
            'last_executed_runtime' => $equipmentRuntimeAtCompletion,
        ];

        if ($plan->frequency_type === 'date' && $plan->interval_days) {
            // Advance from completion date (not from old next_due, to avoid cascading drift)
            $payload['next_due_at'] = $completedAt->copy()->addDays((int) $plan->interval_days)->toDateString();
        }

        if ($plan->frequency_type === 'runtime' && $plan->interval_hours && $equipmentRuntimeAtCompletion !== null) {
            $payload['next_due_runtime'] = round($equipmentRuntimeAtCompletion + (float) $plan->interval_hours, 2);
        }

        $plan->update($payload);
    }

    private function isDue(PmPlan $plan, Carbon $today): bool
    {
        if ($plan->frequency_type === 'date') {
            // First time generation OR next_due_at has arrived/passed
            return $plan->next_due_at === null || $plan->next_due_at->lte($today);
        }

        if ($plan->frequency_type === 'runtime') {
            if ($plan->next_due_runtime === null) {
                // First-time setup requires explicit manual generate — avoid false positives from runtime=0
                return false;
            }
            $current = (float) ($plan->equipment->runtime_hours ?? 0);

            return $current >= (float) $plan->next_due_runtime;
        }

        return false;
    }

    private function hasOpenWo(PmPlan $plan): bool
    {
        return PmWorkOrder::where('pm_plan_id', $plan->id)
            ->whereIn('status', ['due', 'in_progress', 'overdue'])
            ->exists();
    }

    private function dueDateFor(PmPlan $plan): Carbon
    {
        if ($plan->next_due_at) {
            return $plan->next_due_at->copy();
        }

        return now()->startOfDay();
    }

    private function nextCode(): string
    {
        $prefix = 'WO-PM-'.now()->format('Ym').'-';
        $latest = PmWorkOrder::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');
        $seq = $latest ? ((int) substr($latest, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
