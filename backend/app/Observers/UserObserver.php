<?php

namespace App\Observers;

use App\Models\SystemChangeLog;
use App\Models\User;

class UserObserver
{
    private const SKIP_KEYS = ['id', 'created_at', 'updated_at', 'password', 'remember_token', 'api_token'];

    public function created(User $user): void
    {
        SystemChangeLog::record(
            entityType: 'user',
            entityId: $user->auto_code ?? (string) $user->id,
            action: 'created',
            changedFields: [
                'email' => ['from' => null, 'to' => $user->email],
                'name'  => ['from' => null, 'to' => $user->first_name.' '.$user->last_name],
            ],
        );
    }

    public function updated(User $user): void
    {
        $changes = [];
        foreach ($user->getChanges() as $key => $newValue) {
            if (in_array($key, self::SKIP_KEYS, true)) {
                continue;
            }
            $changes[$key] = [
                'from' => $user->getOriginal($key),
                'to'   => $newValue,
            ];
        }
        if (! $changes) {
            return;
        }
        SystemChangeLog::record(
            entityType: 'user',
            entityId: $user->auto_code ?? (string) $user->id,
            action: 'updated',
            changedFields: $changes,
        );
    }

    public function deleted(User $user): void
    {
        SystemChangeLog::record(
            entityType: 'user',
            entityId: $user->auto_code ?? (string) $user->id,
            action: 'deleted',
            changedFields: [
                'email' => ['from' => $user->email, 'to' => null],
                'name'  => ['from' => $user->first_name.' '.$user->last_name, 'to' => null],
            ],
        );
    }
}
