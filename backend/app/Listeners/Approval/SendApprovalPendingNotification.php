<?php

namespace App\Listeners\Approval;

use App\Events\Approval\WorkflowStarted;
use App\Events\Approval\WorkflowStepAdvanced;
use App\Models\ApprovalInstanceStep;
use App\Notifications\ApprovalPendingNotification;
use App\Services\ApproverResolverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendApprovalPendingNotification implements ShouldQueue
{
    public function __construct(
        private readonly ApproverResolverService $resolver,
    ) {}

    public function handle(WorkflowStarted|WorkflowStepAdvanced $event): void
    {
        $instance = $event->instance;

        $step = $event instanceof WorkflowStepAdvanced
            ? $event->nextStep
            : $instance->steps->firstWhere('step_no', $instance->current_step_no);

        if (! $step instanceof ApprovalInstanceStep) {
            return;
        }

        // Recipients = resolved approvers + their active substitutes.
        $approvers = $this->resolver->resolve($step);
        $substitutes = $approvers
            ->map(fn ($u) => \App\Models\UserSubstitution::findActiveSubstitute((int) $u->id, now()))
            ->filter()
            ->map(fn ($id) => \App\Models\User::find($id))
            ->filter();
        $recipients = $approvers->concat($substitutes)->unique('id')->values();

        if ($recipients->isEmpty()) {
            return;
        }

        // Dedup PER RECIPIENT — a blanket instance+step check would let one
        // recipient's earlier notification suppress everyone else's.
        $alreadyNotified = DB::table('notifications')
            ->where('type', ApprovalPendingNotification::class)
            ->whereJsonContains('data->instance_id', $instance->id)
            ->whereJsonContains('data->step_no', $step->step_no)
            ->where('created_at', '>', now()->subMinutes(2))
            ->pluck('notifiable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fresh = $recipients->reject(fn ($u) => in_array((int) $u->id, $alreadyNotified, true));

        if ($fresh->isNotEmpty()) {
            Notification::send($fresh, new ApprovalPendingNotification($instance, $step));
        }
    }
}
