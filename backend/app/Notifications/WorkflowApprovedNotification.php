<?php

namespace App\Notifications;

use App\Models\ApprovalInstance;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalInstance $instance,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)
            ->channels($notifiable->id, 'workflow_approved');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notifications.workflow_approved_title'),
            'body' => __('notifications.workflow_approved_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
            ]),
            'icon' => 'check-circle',
            'document_type' => $this->instance->document_type,
            'reference_no' => $this->instance->reference_no,
            'instance_id' => $this->instance->id,
            'url' => $this->documentUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.workflow_approved_title'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.workflow_approved_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
            ]))
            ->action(__('notifications.view_document'), url($this->documentUrl()));
    }

    public function toLineMessage(object $notifiable): string
    {
        $ref = $this->instance->reference_no ?? "#{$this->instance->id}";

        return "\n"
            . "✅ " . __('notifications.workflow_approved_title') . "\n"
            . __('notifications.workflow_approved_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $ref,
            ]) . "\n"
            . url($this->documentUrl());
    }

    private function documentTypeLabel(): string
    {
        $key = "notifications.document_types.{$this->instance->document_type}";
        if (\Illuminate\Support\Facades\Lang::has($key)) {
            return __($key);
        }
        $formName = \App\Models\DocumentFormSubmission::where('approval_instance_id', $this->instance->id)
            ->with('form:id,name')
            ->first()?->form?->name;
        return $formName ?? $this->instance->document_type;
    }

    private function documentUrl(): string
    {
        return match ($this->instance->document_type) {
            'repair_request' => "/repair-requests/{$this->instance->id}",
            'pm_am_plan' => "/maintenance/{$this->instance->id}",
            'spare_parts_requisition' => "/spare-parts/requisition/{$this->instance->id}",
            default => $this->eformSubmissionUrl(),
        };
    }

    private function eformSubmissionUrl(): string
    {
        $submissionId = \App\Models\DocumentFormSubmission::where('approval_instance_id', $this->instance->id)
            ->value('id');
        return $submissionId
            ? route('forms.submission.show', $submissionId, false)
            : '/approvals/my-approvals';
    }
}
