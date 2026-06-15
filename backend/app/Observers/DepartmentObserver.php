<?php

namespace App\Observers;

use App\Models\Department;
use App\Models\SystemChangeLog;

class DepartmentObserver
{
    private const SKIP_KEYS = ['id', 'created_at', 'updated_at'];

    public function created(Department $department): void
    {
        SystemChangeLog::record(
            entityType: 'department',
            entityId: $department->auto_code ?? $department->name,
            action: 'created',
            changedFields: ['name' => ['from' => null, 'to' => $department->name]],
        );
    }

    public function updated(Department $department): void
    {
        $changes = [];
        foreach ($department->getChanges() as $key => $newValue) {
            if (in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }
            $changes[$key] = [
                'from' => $department->getOriginal($key),
                'to'   => $newValue,
            ];
        }
        if (! $changes) {
            return;
        }
        SystemChangeLog::record(
            entityType: 'department',
            entityId: $department->auto_code ?? $department->name,
            action: 'updated',
            changedFields: $changes,
        );
    }

    public function deleted(Department $department): void
    {
        SystemChangeLog::record(
            entityType: 'department',
            entityId: $department->auto_code ?? $department->name,
            action: 'deleted',
            changedFields: ['name' => ['from' => $department->name, 'to' => null]],
        );
    }
}
