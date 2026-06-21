<?php

namespace App\Notifications;

use App\Models\UserSubstitution;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubstitutionAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly UserSubstitution $substitution,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)
            ->channels($notifiable->id, 'approval_pending');
    }

    public function toArray(object $notifiable): array
    {
        $from = $this->substitution->fromUser;
        $fromName = trim(($from->first_name ?? '').' '.($from->last_name ?? ''));
        $starts = $this->substitution->starts_at->format('d/m/Y');
        $ends = $this->substitution->ends_at?->format('d/m/Y') ?? __('common.no_end_date');

        return [
            'title' => __('notifications.substitution_assigned_title'),
            'body' => __('notifications.substitution_assigned_body', [
                'from_name' => $fromName,
                'starts_at' => $starts,
                'ends_at' => $ends,
            ]),
            'icon' => 'user-group',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['body']);
    }

    public function toLineMessage(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        return "\n".'👥 '.$data['title']."\n".$data['body'];
    }
}
