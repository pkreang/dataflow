<?php

namespace App\Http\Controllers\Web;

use App\Channels\LineMessagingChannel;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\ApplyDatabaseMailConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class NotificationSettingController extends Controller
{
    private const NOTIFICATION_TOGGLE_KEYS = [
        'notifications.email_enabled',
        'notifications.approval_pending_email',
        'notifications.workflow_approved_email',
        'notifications.workflow_rejected_email',
        'line_messaging.enabled',
        'notifications.approval_pending_line',
        'notifications.workflow_approved_line',
        'notifications.workflow_rejected_line',
        'notifications.stock_low_email',
        'notifications.stock_low_line',
    ];

    /** Keys loaded for the settings form (non-secret). */
    private const MAIL_FORM_KEYS = [
        'mail.use_db_settings',
        'mail.mailer',
        'mail.smtp_host',
        'mail.smtp_port',
        'mail.smtp_username',
        'mail.smtp_encryption',
        'mail.from_address',
        'mail.from_name',
    ];

    private const LINE_MESSAGING_FORM_KEYS = [
        'line_messaging.channel_access_token',
        'line_messaging.channel_id',
        'line_messaging.channel_secret',
        'line_login.channel_id',
        'line_login.channel_secret',
    ];

    public function index(): View
    {
        $keys = array_merge(self::NOTIFICATION_TOGGLE_KEYS, self::MAIL_FORM_KEYS, self::LINE_MESSAGING_FORM_KEYS);
        $settings = Setting::whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();

        $smtpPasswordConfigured = Setting::query()
            ->where('key', 'mail.smtp_password_enc')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();

        return view('settings.notifications.index', compact('settings', 'smtpPasswordConfigured'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mail_mailer' => 'required|in:smtp,log,sendmail,array',
            'mail_smtp_host' => 'nullable|string|max:255',
            'mail_smtp_port' => 'nullable|integer|min:1|max:65535',
            'mail_smtp_username' => 'nullable|string|max:255',
            'mail_smtp_password' => 'nullable|string|max:500',
            'mail_smtp_encryption' => 'nullable|in:tls,ssl,none',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'line_messaging_channel_access_token' => 'nullable|string|max:500',
            'line_messaging_channel_id' => 'nullable|string|max:64',
            'line_messaging_channel_secret' => 'nullable|string|max:128',
            'line_login_channel_id' => 'nullable|string|max:64',
            'line_login_channel_secret' => 'nullable|string|max:128',
        ]);

        Setting::updateOrCreate(
            ['key' => 'line_messaging.channel_access_token'],
            ['value' => trim((string) $request->input('line_messaging_channel_access_token', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'line_messaging.channel_id'],
            ['value' => trim((string) $request->input('line_messaging_channel_id', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'line_messaging.channel_secret'],
            ['value' => trim((string) $request->input('line_messaging_channel_secret', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'line_login.channel_id'],
            ['value' => trim((string) $request->input('line_login_channel_id', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'line_login.channel_secret'],
            ['value' => trim((string) $request->input('line_login_channel_secret', ''))]
        );

        $toggles = $request->input('toggle', []);

        foreach (self::NOTIFICATION_TOGGLE_KEYS as $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => ($toggles[$key] ?? '0') === '1' ? '1' : '0']
            );
        }

        Setting::updateOrCreate(
            ['key' => 'mail.use_db_settings'],
            ['value' => ($toggles['mail.use_db_settings'] ?? '0') === '1' ? '1' : '0']
        );

        $port = $request->input('mail_smtp_port');
        $port = $port !== null && $port !== '' ? (int) $port : (int) config('mail.mailers.smtp.port', 587);

        Setting::updateOrCreate(
            ['key' => 'mail.mailer'],
            ['value' => $request->input('mail_mailer', 'smtp')]
        );
        Setting::updateOrCreate(
            ['key' => 'mail.smtp_host'],
            ['value' => trim((string) $request->input('mail_smtp_host', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'mail.smtp_port'],
            ['value' => (string) $port]
        );
        Setting::updateOrCreate(
            ['key' => 'mail.smtp_username'],
            ['value' => trim((string) $request->input('mail_smtp_username', ''))]
        );

        $encInput = (string) $request->input('mail_smtp_encryption', 'none');
        Setting::updateOrCreate(
            ['key' => 'mail.smtp_encryption'],
            ['value' => $encInput === 'none' ? '' : $encInput]
        );

        Setting::updateOrCreate(
            ['key' => 'mail.from_address'],
            ['value' => trim((string) $request->input('mail_from_address', ''))]
        );
        Setting::updateOrCreate(
            ['key' => 'mail.from_name'],
            ['value' => trim((string) $request->input('mail_from_name', ''))]
        );

        if ($request->filled('mail_smtp_password')) {
            Setting::updateOrCreate(
                ['key' => 'mail.smtp_password_enc'],
                ['value' => encrypt($request->input('mail_smtp_password'))]
            );
        }

        $cacheKeys = array_merge(
            self::NOTIFICATION_TOGGLE_KEYS,
            self::MAIL_FORM_KEYS,
            self::LINE_MESSAGING_FORM_KEYS,
            ['mail.smtp_password_enc']
        );
        foreach ($cacheKeys as $key) {
            Cache::forget("setting.{$key}");
        }

        ApplyDatabaseMailConfig::apply();

        return redirect()
            ->route('settings.notifications.index')
            ->with('success', __('notifications.settings_saved'));
    }

    public function testLineSend(Request $request): RedirectResponse
    {
        $userId = (int) (session('user')['id'] ?? 0);
        $admin = User::find($userId);

        if (! $admin) {
            return redirect()->route('settings.notifications.index')
                ->with('error', __('auth.unauthenticated'));
        }

        $token = Setting::get('line_messaging.channel_access_token');
        if (! $token) {
            return redirect()->route('settings.notifications.index')
                ->with('error', __('notifications.line_test_send_no_token'));
        }

        if (! $admin->line_user_id) {
            return redirect()->route('settings.notifications.index')
                ->with('error', __('notifications.line_test_send_no_user_id'));
        }

        $notification = new class extends Notification {
            public function toLineMessage(object $notifiable): string
            {
                return __('notifications.line_test_send_message', ['app' => config('app.name')]);
            }
        };

        (new LineMessagingChannel())->send($admin, $notification);

        return redirect()->route('settings.notifications.index')
            ->with('success', __('notifications.line_test_send_dispatched'));
    }
}
