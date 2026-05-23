<?php

namespace App\Services\Auth;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LINE Login (OAuth 2.1) — used for **linking** a logged-in app user's
 * account to their LINE userId, so the LINE Messaging API channel can
 * push messages to them.
 *
 * This is NOT a login flow into the app — the user must already be
 * authenticated. Callback stores `users.line_user_id` then redirects
 * back to the profile page.
 *
 * Docs: https://developers.line.biz/en/docs/line-login/
 */
class LineLoginService
{
    private const AUTHORIZE_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_URL = 'https://api.line.me/oauth2/v2.1/token';
    private const PROFILE_URL = 'https://api.line.me/v2/profile';
    private const SCOPES = 'profile openid';
    private const SESSION_STATE_KEY = 'oauth_line_login_state';

    public function authorizationUrl(): string
    {
        $channelId = (string) Setting::get('line_login.channel_id');
        $redirectUri = route('auth.line.callback', [], true);
        $state = Str::random(40);
        session([self::SESSION_STATE_KEY => $state]);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $channelId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => self::SCOPES,
        ]);
    }

    /**
     * @return array{success: true, line_user_id: string, display_name: string}|array{success: false, message: string}
     */
    public function handleCallback(Request $request): array
    {
        $sessionState = session()->pull(self::SESSION_STATE_KEY);
        if (! $sessionState || ! hash_equals((string) $sessionState, (string) $request->query('state', ''))) {
            return ['success' => false, 'message' => __('auth.oauth_state_invalid')];
        }

        if ($request->query('error')) {
            return [
                'success' => false,
                'message' => (string) $request->query('error_description', $request->query('error')),
            ];
        }

        $code = $request->query('code');
        if (! $code) {
            return ['success' => false, 'message' => __('auth.oauth_missing_code')];
        }

        $channelId = (string) Setting::get('line_login.channel_id');
        $channelSecret = (string) Setting::get('line_login.channel_secret');
        $redirectUri = route('auth.line.callback', [], true);

        $tokenResponse = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $channelId,
            'client_secret' => $channelSecret,
        ]);

        if (! $tokenResponse->successful()) {
            return ['success' => false, 'message' => __('auth.oauth_token_failed')];
        }

        $accessToken = $tokenResponse->json('access_token');
        if (! $accessToken) {
            return ['success' => false, 'message' => __('auth.oauth_token_failed')];
        }

        $profileResponse = Http::withToken($accessToken)->get(self::PROFILE_URL);
        if (! $profileResponse->successful()) {
            return ['success' => false, 'message' => __('auth.oauth_profile_incomplete')];
        }

        $profile = $profileResponse->json();
        $userId = (string) ($profile['userId'] ?? '');
        if ($userId === '') {
            return ['success' => false, 'message' => __('auth.oauth_profile_incomplete')];
        }

        return [
            'success' => true,
            'line_user_id' => $userId,
            'display_name' => (string) ($profile['displayName'] ?? ''),
        ];
    }
}
