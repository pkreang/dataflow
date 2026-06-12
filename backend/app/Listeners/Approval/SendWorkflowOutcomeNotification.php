<?php

namespace App\Listeners\Approval;

use App\Events\Approval\WorkflowCompleted;
use App\Notifications\WorkflowApprovedNotification;
use App\Notifications\WorkflowRejectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWorkflowOutcomeNotification implements ShouldQueue
{
    public function handle(WorkflowCompleted $event): void
    {
        $instance = $event->instance;
        $requester = $instance->requester;

        if (! $requester) {
            return;
        }

        $notification = $event->outcome === 'approved'
            ? new WorkflowApprovedNotification($instance)
            : new WorkflowRejectedNotification($instance, $event->comment);

        $requester->notify($notification);

        // On-behalf: the person who actually filed the document gets the
        // outcome too, so they can follow up for the owner.
        $creator = $instance->formSubmission?->createdBy;
        if ($creator && (int) $creator->id !== (int) $requester->id) {
            $creator->notify(clone $notification);
        }
    }
}
