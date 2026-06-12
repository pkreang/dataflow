<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationCleanup
{
    /**
     * Mark bell notifications that point at the given approval instance as
     * read — for one user, or for every recipient when $userId is null
     * (e.g. the workflow completed and nobody can act anymore). Read, not
     * deleted: history stays on the notifications index page.
     *
     * All approval notification types carry `instance_id` in their data
     * payload (ApprovalPending / EscalationReminder / WorkflowApproved /
     * WorkflowRejected), so one data-key match covers them all.
     */
    public static function markInstanceRead(int $instanceId, ?int $userId = null): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('notifiable_type', User::class)
            ->when($userId !== null, fn ($q) => $q->where('notifiable_id', $userId))
            ->where('data->instance_id', $instanceId)
            ->update(['read_at' => now()]);
    }
}
