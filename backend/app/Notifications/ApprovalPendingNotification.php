<?php

namespace App\Notifications;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalPendingNotification extends Notification implements ShouldQueue
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
            'title' => __('notifications.approval_pending_title'),
            'body' => __('notifications.approval_pending_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
                'step' => $this->step->stage_name,
            ]),
            'icon' => 'clipboard-check',
            'document_type' => $this->instance->document_type,
            'reference_no' => $this->instance->reference_no,
            'instance_id' => $this->instance->id,
            'step_no' => $this->step->step_no,
            'url' => $this->documentUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.approval_pending_title'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.approval_pending_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $this->instance->reference_no ?? "#{$this->instance->id}",
                'step' => $this->step->stage_name,
            ]))
            ->action(__('notifications.view_document'), url($this->documentUrl()));
    }

    public function toLineMessages(object $notifiable): array
    {
        $ref   = $this->instance->reference_no ?? "#{$this->instance->id}";
        $type  = $this->documentTypeLabel();
        $stage = $this->step->stage_name ?? '';
        $data  = "a=approve&i={$this->instance->id}&s={$this->step->step_no}";
        $rdata = "a=reject&i={$this->instance->id}&s={$this->step->step_no}";

        return [[
            'type'     => 'flex',
            'altText'  => "รออนุมัติ {$ref}",
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => '#2563EB',
                    'paddingAll'      => 'lg',
                    'contents'        => [[
                        'type'   => 'text',
                        'text'   => 'รออนุมัติเอกสาร',
                        'color'  => '#FFFFFF',
                        'weight' => 'bold',
                        'size'   => 'md',
                    ]],
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'sm',
                    'paddingAll' => 'lg',
                    'contents' => [
                        ['type'=>'text','text'=>$ref,'weight'=>'bold','size'=>'xl','wrap'=>true],
                        ['type'=>'text','text'=>$type,'color'=>'#6B7280','size'=>'sm'],
                        ['type'=>'text','text'=>"ขั้นตอน: {$stage}",'size'=>'sm','color'=>'#374151'],
                    ],
                ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'horizontal',
                    'spacing'  => 'sm',
                    'paddingAll' => 'lg',
                    'contents' => [
                        [
                            'type'   => 'button',
                            'style'  => 'primary',
                            'color'  => '#10B981',
                            'height' => 'sm',
                            'action' => [
                                'type'        => 'postback',
                                'label'       => 'อนุมัติ',
                                'data'        => $data,
                                'displayText' => 'อนุมัติแล้ว',
                            ],
                        ],
                        [
                            'type'   => 'button',
                            'style'  => 'primary',
                            'color'  => '#EF4444',
                            'height' => 'sm',
                            'action' => [
                                'type'        => 'postback',
                                'label'       => 'ไม่อนุมัติ',
                                'data'        => $rdata,
                                'displayText' => 'ไม่อนุมัติ',
                            ],
                        ],
                    ],
                ],
            ],
        ]];
    }

    public function toLineMessage(object $notifiable): string
    {
        $ref = $this->instance->reference_no ?? "#{$this->instance->id}";

        return "\n"
            . "รออนุมัติ: " . __('notifications.approval_pending_title') . "\n"
            . __('notifications.approval_pending_body', [
                'document_type' => $this->documentTypeLabel(),
                'reference' => $ref,
                'step' => $this->step->stage_name,
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
