<?php

namespace App\Channels;

use App\Models\Setting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingChannel
{
    private const ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    public function send(object $notifiable, Notification $notification): void
    {
        $token = Setting::get('line_messaging.channel_access_token');
        if (! $token) {
            return;
        }

        $userId = $notifiable->line_user_id ?? null;
        if (! $userId) {
            return;
        }

        $text = method_exists($notification, 'toLineMessage')
            ? $notification->toLineMessage($notifiable)
            : $this->fallbackMessage($notification, $notifiable);

        if (! $text) {
            return;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'to' => $userId,
                    'messages' => [['type' => 'text', 'text' => $text]],
                ]);

            if ($response->failed()) {
                Log::warning('LINE Messaging push failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $notifiable->id ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('LINE Messaging push error', [
                'message' => $e->getMessage(),
                'user_id' => $notifiable->id ?? null,
            ]);
        }
    }

    private function fallbackMessage(Notification $notification, object $notifiable): ?string
    {
        $data = method_exists($notification, 'toArray')
            ? $notification->toArray($notifiable)
            : null;

        if (! $data) {
            return null;
        }

        $parts = [($data['title'] ?? '')];
        if ($data['body'] ?? null) {
            $parts[] = $data['body'];
        }
        if ($data['url'] ?? null) {
            $parts[] = url($data['url']);
        }

        return implode("\n", array_filter($parts));
    }
}
