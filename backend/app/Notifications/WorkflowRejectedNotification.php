<?php

namespace App\Notifications;

use App\Models\ApprovalInstance;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalInstance $instance,
        public readonly ?string $comment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)
            ->channels($notifiable->id, 'workflow_rejected');
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'title' => __('notifications.workflow_rejected_title'),
            'body' => __('notifications.workflow_rejected_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
            ]),
            'icon' => 'x-circle',
            'document_type' => $this->instance->document_type,
            'reference_no' => $this->instance->reference_no,
            'instance_id' => $this->instance->id,
            'url' => $this->documentUrl(),
        ];

        if ($this->comment) {
            $data['comment'] = $this->comment;
        }

        return $data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('notifications.workflow_rejected_title'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.workflow_rejected_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
            ]));

        if ($this->comment) {
            $mail->line(__('notifications.rejection_comment', ['comment' => $this->comment]));
        }

        return $mail->action(__('notifications.view_document'), url($this->documentUrl()));
    }

    public function toLineMessage(object $notifiable): string
    {
        $ref = $this->instance->reference_no ?? "#{$this->instance->id}";

        $msg = "\n"
            .'❌ '.__('notifications.workflow_rejected_title')."\n"
            .__('notifications.workflow_rejected_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $ref,
            ]);

        if ($this->comment) {
            $msg .= "\n".__('notifications.rejection_comment', ['comment' => $this->comment]);
        }

        return $msg."\n".url($this->documentUrl());
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
