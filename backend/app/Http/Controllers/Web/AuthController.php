<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\AuthModeService;
use App\Services\Auth\EntraOAuthService;
use App\Services\Auth\LdapAuthService;
use App\Services\Auth\PasswordCapabilityService;
use App\Services\Auth\LoginHistoryRecorder;
use App\Services\Auth\PasswordLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (session('api_token')) {
            return redirect()->route('dashboard');
        }

        $systemLogo = Setting::get('system_logo');
        $loginBackground = Setting::get('login_background');
        $loginBackgroundColor = Setting::get('login_background_color', '#2563eb');
        $loginIllustration = Setting::get('login_illustration');

        $authLocalEnabled = AuthModeService::isLocalEnabled();
        $authEntraEnabled = AuthModeService::isEntraEnabled() && AuthModeService::entraConfigured();
        $authLdapEnabled = AuthModeService::isLdapEnabled() && AuthModeService::ldapConfigured() && extension_loaded('ldap');
        $authConfigured = AuthModeService::anyMethodEnabled()
            && ($authLocalEnabled || $authEntraEnabled || $authLdapEnabled);

        return view('auth.login', compact(
            'systemLogo',
            'loginBackground',
            'loginBackgroundColor',
            'loginIllustration',
            'authLocalEnabled',
            'authEntraEnabled',
            'authLdapEnabled',
            'authConfigured'
        ));
    }

    public function login(Request $request): RedirectResponse
    {
        if (! AuthModeService::isLocalEnabled()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.local_disabled')]);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // เรียก logic login ของ API โดยตรง — ไม่ผ่าน Kernel ซ้ำ (หลีกเลี่ยงปัญหา Host/URI ของ Request ย่อย)
        $apiRequest = Request::create('/api/v1/auth/login', 'POST', [
            'email' => $request->email,
            'password' => $request->password,
            'device_name' => 'web-browser',
        ]);
        $this->forwardLocaleToApiSubrequest($request, $apiRequest);

        $jsonResponse = app(ApiAuthController::class)->login($apiRequest);
        $data = $jsonResponse->getData(true);

        if (! is_array($data) || ! ($data['success'] ?? false)) {
            $message = is_array($data) ? ($data['message'] ?? __('auth.failed')) : __('auth.failed');
            if ($jsonResponse->getStatusCode() === 403 && is_array($data) && isset($data['message'])) {
                $message = $data['message'];
            }

            // Record failed local login so the user + admins can spot attacks.
            $maybeUser = User::where('email', $request->email)->first();
            app(LoginHistoryRecorder::class)->recordFailure(
                $request->email,
                $maybeUser,
                $request,
                'local',
                $this->classifyFailureReason($jsonResponse->getStatusCode(), $data)
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $message]);
        }

        $userData = $data['data']['user'] ?? $data['data'] ?? [];

        $token = $data['data']['token'] ?? null;
        if (isset($userData['id'])) {
            $user = User::find($userData['id']);
            if ($user) {
                app(LoginHistoryRecorder::class)->recordSuccess($user, $request, 'local');
            }
        }
        if ($token) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($tokenModel) {
                $this->stampTokenOrigin($tokenModel->id, $request);
            }
        }

        return $this->establishSessionFromUserArray($request, $token, $userData);
    }

    /**
     * Record the IP + user-agent on the token so users can later review active
     * sessions and revoke specific devices.
     */
    private function stampTokenOrigin(int $tokenId, Request $request): void
    {
        \Laravel\Sanctum\PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->update([
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            ]);
    }

    private function classifyFailureReason(int $status, array $data): string
    {
        if ($status === 403) {
            return 'account_blocked';
        }
        $code = $data['code'] ?? null;
        if (is_string($code)) {
            return $code;
        }

        return 'invalid_credentials';
    }

    public function redirectToEntra(): RedirectResponse
    {
        if (! AuthModeService::isEntraEnabled() || ! AuthModeService::entraConfigured()) {
            return redirect()->route('login')->withErrors(['email' => __('auth.entra_unavailable')]);
        }

        return redirect()->away(app(EntraOAuthService::class)->authorizationUrl());
    }

    public function entraCallback(Request $request): RedirectResponse
    {
        $result = app(EntraOAuthService::class)->handleCallback($request);
        if (! ($result['success'] ?? false)) {
            app(LoginHistoryRecorder::class)->recordFailure(
                $result['email'] ?? null,
                null,
                $request,
                'entra',
                $result['code'] ?? 'entra_callback_failed'
            );

            return redirect()->route('login')->withErrors(['email' => $result['message'] ?? __('auth.failed')]);
        }

        /** @var User $user */
        $user = $result['user'];
        $tokenResult = $user->createToken('web-browser');
        $token = $tokenResult->plainTextToken;
        $this->stampTokenOrigin($tokenResult->accessToken->id, $request);

        app(LoginHistoryRecorder::class)->recordSuccess($user, $request, 'entra');

        return $this->establishSessionFromUserModel($request, $token, $user->fresh());
    }

    public function loginLdap(Request $request): RedirectResponse
    {
        if (! AuthModeService::isLdapEnabled() || ! AuthModeService::ldapConfigured()) {
            return back()
                ->withInput($request->only('ldap_email'))
                ->withErrors(['ldap_email' => __('auth.ldap_unavailable')]);
        }

        if (! extension_loaded('ldap')) {
            return back()
                ->withInput($request->only('ldap_email'))
                ->withErrors(['ldap_email' => __('auth.ldap_extension_missing')]);
        }

        $request->validate([
            'ldap_email' => 'required|email',
            'ldap_password' => 'required',
        ]);

        $user = app(LdapAuthService::class)->attempt(
            $request->input('ldap_email'),
            $request->input('ldap_password')
        );

        if (! $user) {
            app(LoginHistoryRecorder::class)->recordFailure(
                $request->input('ldap_email'),
                null,
                $request,
                'ldap',
                'invalid_credentials'
            );

            return back()
                ->withInput($request->only('ldap_email'))
                ->withErrors(['ldap_email' => __('auth.failed')]);
        }

        $tokenResult = $user->createToken('web-browser');
        $token = $tokenResult->plainTextToken;
        $this->stampTokenOrigin($tokenResult->accessToken->id, $request);

        app(LoginHistoryRecorder::class)->recordSuccess($user, $request, 'ldap');

        return $this->establishSessionFromUserModel($request, $token, $user);
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = session('api_token');
        if ($token) {
            \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->delete();
        }

        session()->forget(['api_token', 'user', 'user_permissions']);

        return redirect()->route('login');
    }

    private function establishSessionFromUserModel(Request $request, ?string $token, User $user): RedirectResponse
    {
        return $this->establishSessionFromUserArray($request, $token, [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'auth_provider' => $user->auth_provider,
            'org_unit_id' => $user->org_unit_id,
            'is_super_admin' => $user->is_super_admin,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $userData
     */
    private function establishSessionFromUserArray(Request $request, ?string $token, array $userData): RedirectResponse
    {
        $roles = $userData['roles'] ?? [];
        $isSuperAdmin = (bool) ($userData['is_super_admin'] ?? false);
        $authProvider = $userData['auth_provider'] ?? null;

        session([
            'api_token' => $token,
            'user' => [
                'id' => $userData['id'] ?? null,
                'first_name' => $userData['first_name'] ?? '',
                'last_name' => $userData['last_name'] ?? '',
                'name' => trim(($userData['first_name'] ?? '').' '.($userData['last_name'] ?? '')) ?: ($userData['name'] ?? ''),
                'email' => $userData['email'] ?? '',
                'avatar' => $userData['avatar'] ?? null,
                'auth_provider' => $authProvider,
                'can_change_password' => PasswordCapabilityService::canChangePasswordFromAuthProvider($authProvider),
                'roles' => $roles,
                'is_super_admin' => $isSuperAdmin,
                'org_unit_id' => $userData['org_unit_id'] ?? null,
            ],
            'user_permissions' => $userData['permissions'] ?? [],
        ]);

        $userId = $userData['id'] ?? null;
        if ($userId) {
            $freshUser = User::query()->find($userId);
            if ($freshUser) {
                if (is_string($freshUser->locale)
                    && $freshUser->locale !== ''
                    && in_array($freshUser->locale, ['th', 'en'], true)) {
                    session(['locale' => $freshUser->locale]);
                    app()->setLocale($freshUser->locale);
                }
            }
            if ($freshUser && PasswordLifecycleService::requiresPasswordChange($freshUser)) {
                return redirect()
                    ->route('profile.password')
                    ->with('warning', __('auth.password_change_required'));
            }
        }

        $intended = session()->pull('intended');
        $baseUrl = $request->getSchemeAndHttpHost();

        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH) ?: $intended;
            $query = parse_url($intended, PHP_URL_QUERY);

            return redirect($baseUrl.$path.($query ? '?'.$query : ''));
        }

        return redirect($baseUrl.'/dashboard');
    }

    /** Match session/UI locale so API responses use the same language as the login form. */
    private function forwardLocaleToApiSubrequest(Request $source, Request $target): void
    {
        $locale = app()->getLocale();
        if (! in_array($locale, ['th', 'en'], true)) {
            return;
        }

        if ($source->headers->has('Accept-Language')) {
            $target->headers->set('Accept-Language', $source->headers->get('Accept-Language'));
        } else {
            $target->headers->set('Accept-Language', $locale);
        }

        $target->headers->set('X-Locale', $locale);
    }
}
