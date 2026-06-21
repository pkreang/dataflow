<?php

namespace App\Observers;

use App\Models\ApprovalWorkflowStage;
use App\Models\SystemChangeLog;

class WorkflowStageObserver
{
    /** Columns we don't bother auditing (timestamps, FK pointers tracked elsewhere). */
    private const SKIP_KEYS = ['id', 'created_at', 'updated_at'];

    public function created(ApprovalWorkflowStage $stage): void
    {
        SystemChangeLog::record(
            entityType: 'workflow_stage',
            entityId: (string) $stage->id,
            action: 'created',
            changedFields: ['workflow_id' => ['from' => null, 'to' => $stage->workflow_id]],
        );
    }

    public function updated(ApprovalWorkflowStage $stage): void
    {
        $changes = $this->collectChanges($stage);
        if (! $changes) {
            return;
        }
        SystemChangeLog::record(
            entityType: 'workflow_stage',
            entityId: (string) $stage->id,
            action: 'updated',
            changedFields: $changes,
        );
    }

    public function deleted(ApprovalWorkflowStage $stage): void
    {
        SystemChangeLog::record(
            entityType: 'workflow_stage',
            entityId: (string) $stage->id,
            action: 'deleted',
            changedFields: ['workflow_id' => ['from' => $stage->workflow_id, 'to' => null]],
        );
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function collectChanges(ApprovalWorkflowStage $stage): array
    {
        $changes = [];
        foreach ($stage->getChanges() as $key => $newValue) {
            if (in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }
            $changes[$key] = [
                'from' => $stage->getOriginal($key),
                'to' => $newValue,
            ];
        }

        return $changes;
    }
}
