<?php

namespace App\Services;

use App\Channels\LineMessagingChannel;
use App\Models\NotificationPreference;
use App\Models\Setting;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * Determine which notification channels are enabled for a user + event type.
     *
     * @return array<string|class-string> e.g. ['database', 'mail', LineMessagingChannel::class]
     */
    public function channels(int $userId, string $eventType): array
    {
        $channels = ['database'];

        if ($this->isEmailEnabled($userId, $eventType)) {
            $channels[] = 'mail';
        }

        if ($this->isLineEnabled($userId, $eventType)) {
            $channels[] = LineMessagingChannel::class;
        }

        return $channels;
    }

    public function isEmailEnabled(int $userId, string $eventType): bool
    {
        if (! $this->systemSetting('notifications.email_enabled', true)) {
            return false;
        }

        if (! $this->systemSetting("notifications.{$eventType}_email", true)) {
            return false;
        }

        return $this->userPreference($userId, $eventType, 'mail', true);
    }

    public function isLineEnabled(int $userId, string $eventType): bool
    {
        // System-wide master toggle (LINE Messaging API)
        if (! $this->systemSetting('line_messaging.enabled', false)) {
            return false;
        }

        // System must have a Channel Access Token configured
        $token = Setting::where('key', 'line_messaging.channel_access_token')->value('value');
        if (! $token) {
            return false;
        }

        // Per-event toggle
        if (! $this->systemSetting("notifications.{$eventType}_line", true)) {
            return false;
        }

        // User must have linked their LINE account (line_user_id populated)
        $hasUserId = User::where('id', $userId)
            ->whereNotNull('line_user_id')
            ->where('line_user_id', '!=', '')
            ->exists();

        if (! $hasUserId) {
            return false;
        }

        return $this->userPreference($userId, $eventType, 'line', true);
    }

    private function userPreference(int $userId, string $eventType, string $channel, bool $default): bool
    {
        $pref = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->first();

        return $pref ? $pref->enabled : $default;
    }

    private function systemSetting(string $key, bool $default): bool
    {
        $value = Setting::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
