<?php

namespace App\Services\Kpi;

use App\Models\DocumentFormSubmission;
use App\Models\KpiCycle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lifecycle service for a KPI cycle — open() spawns one draft submission per
 * assignment so each evaluator gets an editable draft they can find in their
 * /forms/drafts list. close() locks the cycle for reporting.
 *
 * Both transitions are transactional; out-of-order calls throw rather than
 * silently no-op so admins notice the mistake.
 */
class KpiCycleOpener
{
    public function open(KpiCycle $cycle): KpiCycle
    {
        return DB::transaction(function () use ($cycle) {
            $cycle = $cycle->fresh(['assignments']);

            if ($cycle->status !== KpiCycle::STATUS_DRAFT) {
                throw new RuntimeException('Cycle is not in draft');
            }
            if ($cycle->assignments->isEmpty()) {
                throw new RuntimeException('Cycle has no assignments');
            }

            foreach ($cycle->assignments as $assignment) {
                $submission = DocumentFormSubmission::query()->create([
                    'form_id' => $cycle->form_id,
                    'user_id' => $assignment->evaluator_user_id,
                    'payload' => [],
                    'status' => 'draft',
                ]);
                $assignment->update(['submission_id' => $submission->id]);
            }

            $cycle->update([
                'status' => KpiCycle::STATUS_OPEN,
                'opened_at' => now(),
            ]);

            return $cycle->fresh();
        });
    }

    public function close(KpiCycle $cycle): KpiCycle
    {
        if ($cycle->status !== KpiCycle::STATUS_OPEN) {
            throw new RuntimeException('Cycle is not open');
        }

        $cycle->update([
            'status' => KpiCycle::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return $cycle->fresh();
    }
}
