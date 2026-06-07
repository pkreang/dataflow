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

        $recentDuplicate = DB::table('notifications')
            ->where('type', ApprovalPendingNotification::class)
            ->whereJsonContains('data->instance_id', $instance->id)
            ->whereJsonContains('data->step_no', $step->step_no)
            ->where('created_at', '>', now()->subMinutes(2))
            ->exists();

        if ($recentDuplicate) {
            return;
        }

        $approvers = $this->resolver->resolve($step);

        if ($approvers->isNotEmpty()) {
            Notification::send($approvers, new ApprovalPendingNotification($instance, $step));
        }
    }
}
