<?php

namespace App\Console\Commands;

use App\Models\ApprovalInstanceStep;
use App\Models\User;
use App\Notifications\ApprovalEscalationReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendApprovalEscalationReminders extends Command
{
    protected $signature = 'approval:send-escalation-reminders';

    protected $description = 'Send reminder notifications for pending approval steps that have exceeded their escalation threshold';

    public function handle(): int
    {
        $now = Carbon::now();

        $overdueSteps = ApprovalInstanceStep::query()
            ->where('action', 'pending')
            ->whereNotNull('escalation_after_days')
            ->whereNull('escalation_notified_at')
            ->whereHas('approvalInstance', fn ($q) => $q->where('status', 'pending'))
            ->with(['approvalInstance'])
            ->get()
            ->filter(fn (ApprovalInstanceStep $step) => $step->approvalInstance->current_step_no === $step->step_no
                && $step->created_at->copy()->addDays($step->escalation_after_days)->lte($now)
            );

        $notified = 0;

        foreach ($overdueSteps as $step) {
            $recipients = $this->resolveRecipients($step);
            foreach ($recipients as $user) {
                $user->notify(new ApprovalEscalationReminder($step->approvalInstance, $step));
            }
            $step->update(['escalation_notified_at' => $now]);
            $notified++;
        }

        $this->info("Escalation reminders sent for {$notified} step(s).");

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function resolveRecipients(ApprovalInstanceStep $step): \Illuminate\Support\Collection
    {
        return match ($step->approver_type) {
            'user' => User::where('id', (int) $step->approver_ref)->get(),
            'position' => User::where('position_id', (int) $step->approver_ref)
                ->where('is_active', true)->get(),
            'role' => User::role($step->approver_ref)->where('is_active', true)->get(),
            default => collect(),
        };
    }
}
