<?php

namespace App\Notifications;

use App\Channels\LineMessagingChannel;
use Illuminate\Notifications\Notification;

class LineConfirmationNotification extends Notification
{
    public function __construct(public readonly string $message) {}

    public function via(object $notifiable): array
    {
        return [LineMessagingChannel::class];
    }

    public function toLineMessage(object $notifiable): string
    {
        return $this->message;
    }
}
