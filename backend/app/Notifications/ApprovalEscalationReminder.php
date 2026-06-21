<?php

namespace App\Notifications;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalEscalationReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalInstance $instance,
        public readonly ApprovalInstanceStep $step,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)
            ->channels($notifiable->id, 'approval_pending');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notifications.escalation_reminder_title'),
            'body' => __('notifications.escalation_reminder_body', [
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
                'step' => $this->step->stage_name,
                'days' => $this->step->escalation_after_days,
            ]),
            'icon' => 'bell-alert',
            'document_type' => $this->instance->document_type,
            'reference_no' => $this->instance->reference_no,
            'instance_id' => $this->instance->id,
            'step_no' => $this->step->step_no,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.escalation_reminder_title'))
            ->line(__('notifications.escalation_reminder_body', [
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
                'step' => $this->step->stage_name,
                'days' => $this->step->escalation_after_days,
            ]));
    }

    public function toLineMessage(object $notifiable): string
    {
        return "\n"
            .'⏰ '.__('notifications.escalation_reminder_title')."\n"
            .__('notifications.escalation_reminder_body', [
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
                'step' => $this->step->stage_name,
                'days' => $this->step->escalation_after_days,
            ]);
    }
}
