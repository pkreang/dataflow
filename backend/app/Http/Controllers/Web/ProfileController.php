<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\DocumentFormSubmission;
use App\Models\LoginHistory;
use App\Models\NotificationPreference;
use App\Models\Position;
use App\Models\ReportDashboard;
use App\Models\Setting;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use App\Rules\PasswordNotReused;
use App\Rules\PasswordPolicy;
use App\Services\Auth\LineLoginService;
use App\Services\Auth\PasswordCapabilityService;
use App\Services\Auth\PasswordLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    /** Notification preferences matrix shown in profile. One row per event type × channel. */
    private const NOTIFICATION_EVENTS = [
        'approval_pending',
        'workflow_approved',
        'workflow_rejected',
        'stock_low',
    ];

    private const NOTIFICATION_CHANNELS = ['mail', 'line'];

    protected function currentUser(): ?User
    {
        $token = session('api_token');
        if ($token) {
            $model = PersonalAccessToken::findToken($token);

            return $model?->tokenable;
        }
        $userId = session('user')['id'] ?? null;

        return $userId ? User::find($userId) : null;
    }

    public function edit(): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $positions = Position::query()
            ->where(function ($q) use ($user) {
                $q->where('is_active', true);
                if ($user->position_id) {
                    $q->orWhere('id', $user->position_id);
                }
            })
            ->orderBy('name')
            ->get();

        $loginHistory = $this->loadLoginHistorySummary($user);

        $availableHomeDashboards = ReportDashboard::query()
            ->where('is_active', true)
            ->accessibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('profile.edit', [
            'user' => $user,
            'positions' => $positions,
            'canChangePasswordInApp' => PasswordCapabilityService::canChangePasswordInApp($user),
            'canEditEmail' => PasswordCapabilityService::canEditEmailInApp($user),
            'isSsoUser' => $this->isSsoUser($user),
            'authPasswordHelpUrl' => trim((string) Setting::get('auth_password_help_url', '')),
            'availableLocales' => ['th' => 'ไทย', 'en' => 'English'],
            'notificationPreferences' => $this->loadNotificationMatrix($user),
            'notificationEvents' => self::NOTIFICATION_EVENTS,
            'notificationChannels' => self::NOTIFICATION_CHANNELS,
            'quickStats' => $this->quickStatsFor($user),
            'lastPriorLogin' => $loginHistory['last'],
            'recentFailedLogins' => $loginHistory['recent_failures'],
            'availableHomeDashboards' => $availableHomeDashboards,
        ]);
    }

    /**
     * Set the user's preferred home dashboard. Validates that the dashboard
     * exists *and* is visible to the user, so a non-admin can't pin a
     * permission-gated dashboard they couldn't actually open. Passing a null
     * value clears the override and falls back to the global default.
     */
    public function updateHomeDashboard(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'home_dashboard_id' => 'nullable|integer|exists:report_dashboards,id',
        ]);

        $dashboardId = $validated['home_dashboard_id'] ?? null;

        if ($dashboardId) {
            $dashboard = ReportDashboard::find($dashboardId);
            if (! $dashboard || ! $dashboard->canBeAccessedBy($user)) {
                return back()->withErrors([
                    'home_dashboard_id' => __('common.dashboard_not_accessible'),
                ]);
            }
        }

        $user->update(['home_dashboard_id' => $dashboardId]);

        return back()->with('success', __('common.saved'));
    }

    public function activeSessions(): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $currentToken = session('api_token');
        $currentTokenId = $currentToken
            ? PersonalAccessToken::findToken($currentToken)?->id
            : null;

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return view('profile.active-sessions', compact('user', 'tokens', 'currentTokenId'));
    }

    public function revokeSession(Request $request, int $tokenId): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        // Never revoke the current session through this endpoint — use logout for that.
        $currentToken = session('api_token');
        $currentTokenId = $currentToken ? PersonalAccessToken::findToken($currentToken)?->id : null;

        $token = PersonalAccessToken::query()
            ->where('id', $tokenId)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->first();
        abort_unless($token, 404);
        if ($token->id === $currentTokenId) {
            return back()->withErrors(['session' => __('common.session_cannot_revoke_current')]);
        }

        $token->delete();

        return back()->with('success', __('common.session_revoked'));
    }

    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $currentTokenId = PersonalAccessToken::findToken(session('api_token'))?->id;

        $count = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return back()->with('success', __('common.session_revoked_others', ['count' => $count]));
    }

    /**
     * Personal API tokens — separate from web browser sessions. Users create, name,
     * and revoke tokens for their own integrations. The token string is shown only
     * once (on creation) via flash — we never store plaintext.
     */
    public function apiTokens(): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', 'like', 'personal:%')
            ->orderByDesc('created_at')
            ->get();

        return view('profile.api-tokens', compact('user', 'tokens'));
    }

    public function createApiToken(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $expiresAt = ! empty($validated['expires_days'])
            ? now()->addDays((int) $validated['expires_days'])
            : null;

        $result = $user->createToken('personal:'.$validated['name'], ['*'], $expiresAt);

        return redirect()
            ->route('profile.api-tokens')
            ->with('new_api_token', $result->plainTextToken)
            ->with('success', __('common.api_token_created'));
    }

    public function revokeApiToken(int $tokenId): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $token = PersonalAccessToken::query()
            ->where('id', $tokenId)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', 'like', 'personal:%')
            ->first();
        abort_unless($token, 404);

        $token->delete();

        return back()->with('success', __('common.api_token_revoked'));
    }

    public function loginHistory(): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $entries = LoginHistory::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('profile.login-history', compact('user', 'entries'));
    }

    /**
     * Unified per-user activity timeline merging submission events + login
     * attempts. Caps each source at 200 most-recent rows to avoid runaway
     * payloads; full-pagination via UNION is in the deferred backlog.
     */
    public function activity(\Illuminate\Http\Request $request): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $kindFilter = (string) $request->query('kind', 'all');

        $submissionRows = SubmissionActivityLog::query()
            ->where('user_id', $user->id)
            ->with('submission.form')
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'kind' => 'submission',
                'when' => $r->created_at,
                'action' => $r->action,
                'subject' => $r->submission?->reference_no ?? '#'.$r->submission_id,
                'subject_secondary' => $r->submission?->form?->name,
                'href' => $r->submission ? route('forms.submission.show', $r->submission) : null,
                'meta' => $r->meta,
            ]);

        $loginRows = LoginHistory::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'kind' => 'login',
                'when' => $r->created_at,
                'action' => 'login_'.$r->result,
                'subject' => $r->ip_address,
                'subject_secondary' => $r->auth_provider,
                'href' => null,
                'meta' => array_filter([
                    'provider' => $r->auth_provider,
                    'failure_reason' => $r->failure_reason,
                ]),
            ]);

        $items = collect()
            ->merge($submissionRows)
            ->merge($loginRows)
            ->sortByDesc(fn ($r) => $r['when']?->getTimestamp() ?? 0)
            ->when($kindFilter === 'submission', fn ($c) => $c->where('kind', 'submission'))
            ->when($kindFilter === 'login', fn ($c) => $c->where('kind', 'login'))
            ->values();

        return view('profile.activity', compact('user', 'items', 'kindFilter'));
    }

    /**
     * Most recent successful login BEFORE the current session starts (so user sees
     * "last time you were here"), plus failed attempts in the last 24h for alerting.
     */
    private function loadLoginHistorySummary(User $user): array
    {
        $successes = LoginHistory::query()
            ->where('user_id', $user->id)
            ->where('result', 'success')
            ->latest('created_at')
            ->limit(2)
            ->get();
        // If the most recent is the current session, show the one before it.
        $lastPrior = $successes->get(1) ?? $successes->first();

        $recentFailures = LoginHistory::query()
            ->where('user_id', $user->id)
            ->where('result', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return ['last' => $lastPrior, 'recent_failures' => $recentFailures];
    }

    /**
     * Build a matrix of current preference states. Missing rows default to "on"
     * (mirrors NotificationPreferenceService::userPreference default).
     *
     * @return array<string, array<string, bool>>
     */
    private function loadNotificationMatrix(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($p) => $p->event_type.'|'.$p->channel);

        $matrix = [];
        foreach (self::NOTIFICATION_EVENTS as $event) {
            foreach (self::NOTIFICATION_CHANNELS as $channel) {
                $key = $event.'|'.$channel;
                $matrix[$event][$channel] = isset($stored[$key]) ? (bool) $stored[$key]->enabled : true;
            }
        }

        return $matrix;
    }

    /**
     * Mirrors ApprovalController::myApprovals filtering so the profile card matches
     * the actual "My Approvals" page count. Kept as a minimal query — no eager loads.
     */
    private function countPendingApprovalsFor(User $user): int
    {
        return ApprovalInstance::query()
            ->pendingForApprover($user->id, $user->roles()->pluck('name')->all(), $user->position_id)
            ->count();
    }

    private function isSsoUser(User $user): bool
    {
        return ! blank($user->auth_provider);
    }

    private function quickStatsFor(User $user): array
    {
        $draftCount = DocumentFormSubmission::where('user_id', $user->id)
            ->where('status', 'draft')
            ->count();
        $submittedCount = DocumentFormSubmission::where('user_id', $user->id)
            ->where('status', 'submitted')
            ->count();

        $pendingApprovals = $this->countPendingApprovalsFor($user);

        return [
            'draft' => $draftCount,
            'submitted' => $submittedCount,
            'pending_approvals' => $pendingApprovals,
        ];
    }

    public function showPasswordForm(): View|RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! PasswordCapabilityService::canChangePasswordInApp($user)) {
            return redirect()
                ->route('profile.edit')
                ->with('info', __('auth.password_change_unavailable_hint'));
        }

        $passwordPolicy = $this->getPasswordPolicyRules();
        $passwordChangeMandatory = PasswordLifecycleService::requiresPasswordChange($user);

        return view('profile.password', compact('passwordPolicy', 'passwordChangeMandatory'));
    }

    private function getPasswordPolicyRules(): array
    {
        $min = Setting::getInt('password_min_length', 8);
        $max = Setting::getInt('password_max_length', 255);
        $rules = [];

        $rules[] = __('password_policy.rule_min_chars', ['min' => $min]);
        $rules[] = __('password_policy.rule_max_chars', ['max' => $max]);
        if (Setting::getBool('password_require_uppercase')) {
            $rules[] = __('password_policy.rule_uppercase');
        }
        if (Setting::getBool('password_require_lowercase')) {
            $rules[] = __('password_policy.rule_lowercase');
        }
        if (Setting::getBool('password_require_number')) {
            $rules[] = __('password_policy.rule_number');
        }
        if (Setting::getBool('password_require_special')) {
            $rules[] = __('password_policy.rule_special');
        }
        $expiry = Setting::getInt('password_expires_days', 0);
        if ($expiry > 0) {
            $rules[] = __('password_policy.rule_expiry', ['days' => $expiry]);
        }
        if (Setting::getBool('password_force_change_first_login')) {
            $rules[] = __('password_policy.rule_first_login');
        }
        $reuse = Setting::getInt('password_prevent_reuse', 0);
        if ($reuse > 0) {
            $rules[] = __('password_policy.rule_reuse', ['n' => $reuse]);
        }

        return $rules;
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $canEditEmail = PasswordCapabilityService::canEditEmailInApp($user);
        $isSso = $this->isSsoUser($user);

        // Locked fields are dropped from input before validation so clients that
        // bypass the UI (inspect → remove readonly → submit) still can't change them.
        $lockedKeys = ['department_id', 'position_id'];
        if ($isSso) {
            // SSO users: names are managed by the identity provider.
            $lockedKeys = array_merge($lockedKeys, ['first_name', 'last_name']);
        }
        $input = \Illuminate\Support\Arr::except($request->all(), $lockedKeys);

        $rules = [
            'phone' => 'nullable|string|max:50',
            'locale' => ['nullable', 'string', Rule::in(['th', 'en'])],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'remove_signature' => 'nullable|boolean',
            'theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
            'density' => ['nullable', 'string', Rule::in(['comfortable', 'compact'])],
        ];
        if (! $isSso) {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
        }
        if ($canEditEmail) {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }
        $validator = validator($input, $rules);
        $validator->validate();

        $payload = [
            'phone' => $input['phone'] ?? null ?: null,
        ];
        if (! $isSso) {
            $payload['first_name'] = $input['first_name'];
            $payload['last_name'] = $input['last_name'];
        }
        if ($canEditEmail) {
            $payload['email'] = $input['email'];
        }
        if (! empty($input['locale'])) {
            $payload['locale'] = $input['locale'];
        }
        if (! empty($input['theme'])) {
            $payload['theme'] = $input['theme'];
        }
        if (! empty($input['density'])) {
            $payload['density'] = $input['density'];
        }

        // Avatar: upload new / remove existing / leave alone
        if ($request->boolean('remove_avatar') && $user->avatar) {
            $this->deleteAvatar($user->avatar);
            $payload['avatar'] = null;
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $this->deleteAvatar($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $payload['avatar'] = Storage::disk('public')->url($path);
        }

        // Signature: same lifecycle as avatar — upload, replace, or remove.
        if ($request->boolean('remove_signature') && $user->signature_path) {
            $this->deleteAvatar($user->signature_path);
            $payload['signature_path'] = null;
        }
        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                $this->deleteAvatar($user->signature_path);
            }
            $path = $request->file('signature')->store('signatures', 'public');
            $payload['signature_path'] = Storage::disk('public')->url($path);
        }

        $user->update($payload);

        $sessionUser = session('user', []);
        $sessionUser['first_name'] = $user->first_name;
        $sessionUser['last_name'] = $user->last_name;
        $sessionUser['name'] = $user->full_name;
        $sessionUser['email'] = $user->email;
        $sessionUser['avatar'] = $user->avatar;
        $sessionUser['theme'] = $user->theme;
        $sessionUser['density'] = $user->density;
        session(['user' => $sessionUser]);

        if ($request->filled('locale')) {
            session(['locale' => $request->locale]);
            app()->setLocale($request->locale);
        }

        return back()->with('success', __('common.profile_updated'));
    }

    /**
     * Replace the user's notification preference matrix with the posted state.
     * Missing event/channel combos are treated as "off" (opt-out).
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $posted = $request->input('notifications', []);
        foreach (self::NOTIFICATION_EVENTS as $event) {
            foreach (self::NOTIFICATION_CHANNELS as $channel) {
                $enabled = (bool) ($posted[$event][$channel] ?? false);
                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'event_type' => $event, 'channel' => $channel],
                    ['enabled' => $enabled]
                );
            }
        }

        return back()->with('success', __('common.notification_prefs_saved'));
    }

    private function deleteAvatar(string $url): void
    {
        // url() returns absolute URL — convert back to relative disk path
        $publicPrefix = Storage::disk('public')->url('');
        $path = str_starts_with($url, $publicPrefix)
            ? ltrim(substr($url, strlen($publicPrefix)), '/')
            : $url;
        Storage::disk('public')->delete($path);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! PasswordCapabilityService::canChangePasswordInApp($user)) {
            return redirect()
                ->route('profile.edit')
                ->with('info', __('auth.password_change_unavailable_hint'));
        }

        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', new PasswordPolicy, new PasswordNotReused($user)],
        ]);

        if (! Hash::check($request->current_password, $user->getRawOriginal('password'))) {
            return back()->withErrors(['current_password' => __('common.current_password_incorrect')]);
        }

        $wasForced = (bool) $user->password_must_change;

        PasswordLifecycleService::applySelfServicePasswordChange($user, $request->password);

        if ($wasForced) {
            $request->session()->forget(['user', 'api_token', 'user_permissions']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', __('auth.password_changed_please_relogin'));
        }

        $request->session()->regenerate();

        return back()->with('success', __('common.password_changed'));
    }

    public function lineLinkRedirect(LineLoginService $service): RedirectResponse
    {
        if (! $this->currentUser()) {
            return redirect()->route('login');
        }

        $channelId = (string) Setting::get('line_login.channel_id');
        if (! $channelId) {
            return redirect()->route('profile.edit')
                ->with('error', __('notifications.line_link_not_configured'));
        }

        return redirect()->away($service->authorizationUrl());
    }

    public function lineLinkCallback(Request $request, LineLoginService $service): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $result = $service->handleCallback($request);
        if (! $result['success']) {
            return redirect()->route('profile.edit')
                ->with('error', $result['message']);
        }

        $user->update(['line_user_id' => $result['line_user_id']]);

        return redirect()->route('profile.edit')
            ->with('success', __('notifications.line_link_success', [
                'name' => $result['display_name'],
            ]));
    }

    public function lineUnlink(): RedirectResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return redirect()->route('login');
        }

        $user->update(['line_user_id' => null]);

        return redirect()->route('profile.edit')
            ->with('success', __('notifications.line_unlink_success'));
    }
}
