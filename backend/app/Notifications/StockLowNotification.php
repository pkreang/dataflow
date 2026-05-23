<?php

namespace App\Notifications;

use App\Models\SparePart;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockLowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SparePart $sparePart,
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)
            ->channels($notifiable->id, 'stock_low');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notifications.stock_low_title'),
            'body' => __('notifications.stock_low_body', [
                'name' => $this->sparePart->name,
                'current' => number_format((float) $this->sparePart->current_stock, 2, '.', ''),
                'min' => number_format((float) $this->sparePart->min_stock, 2, '.', ''),
                'unit' => $this->sparePart->unit,
            ]),
            'icon' => 'alert-triangle',
            'url' => '/spare-parts/stock',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.stock_low_title'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.stock_low_body', [
                'name' => $this->sparePart->name,
                'current' => number_format((float) $this->sparePart->current_stock, 2, '.', ''),
                'min' => number_format((float) $this->sparePart->min_stock, 2, '.', ''),
                'unit' => $this->sparePart->unit,
            ]))
            ->action(__('notifications.view_document'), url('/spare-parts/stock'));
    }

    public function toLineMessage(object $notifiable): string
    {
        return "\n"
            .'⚠️ '.__('notifications.stock_low_title')."\n"
            .__('notifications.stock_low_body', [
                'name' => $this->sparePart->name,
                'current' => number_format((float) $this->sparePart->current_stock, 2, '.', ''),
                'min' => number_format((float) $this->sparePart->min_stock, 2, '.', ''),
                'unit' => $this->sparePart->unit,
            ])."\n"
            .url('/spare-parts/stock');
    }
}
