<?php

namespace App\Observers;

use App\Models\Position;
use App\Models\SystemChangeLog;

class PositionObserver
{
    private const SKIP_KEYS = ['id', 'created_at', 'updated_at'];

    public function created(Position $position): void
    {
        SystemChangeLog::record(
            entityType: 'position',
            entityId: $position->auto_code ?? $position->name,
            action: 'created',
            changedFields: ['name' => ['from' => null, 'to' => $position->name]],
        );
    }

    public function updated(Position $position): void
    {
        $changes = [];
        foreach ($position->getChanges() as $key => $newValue) {
            if (in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }
            $changes[$key] = [
                'from' => $position->getOriginal($key),
                'to'   => $newValue,
            ];
        }
        if (! $changes) {
            return;
        }
        SystemChangeLog::record(
            entityType: 'position',
            entityId: $position->auto_code ?? $position->name,
            action: 'updated',
            changedFields: $changes,
        );
    }

    public function deleted(Position $position): void
    {
        SystemChangeLog::record(
            entityType: 'position',
            entityId: $position->auto_code ?? $position->name,
            action: 'deleted',
            changedFields: ['name' => ['from' => $position->name, 'to' => null]],
        );
    }
}
