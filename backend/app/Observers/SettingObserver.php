<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\SystemChangeLog;

class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $original = $setting->getOriginal('value');
        $new = $setting->value;

        // Only log when the value actually moved. Setting::set() calls
        // updateOrCreate, which fires saved() even on no-op.
        if ($setting->wasRecentlyCreated) {
            SystemChangeLog::record(
                entityType: 'setting',
                entityId: $setting->key,
                action: 'created',
                changedFields: ['value' => ['from' => null, 'to' => $new]],
            );

            return;
        }

        if ((string) $original !== (string) $new) {
            SystemChangeLog::record(
                entityType: 'setting',
                entityId: $setting->key,
                action: 'updated',
                changedFields: ['value' => ['from' => $original, 'to' => $new]],
            );
        }
    }

    public function deleted(Setting $setting): void
    {
        SystemChangeLog::record(
            entityType: 'setting',
            entityId: $setting->key,
            action: 'deleted',
            changedFields: ['value' => ['from' => $setting->value, 'to' => null]],
        );
    }
}
