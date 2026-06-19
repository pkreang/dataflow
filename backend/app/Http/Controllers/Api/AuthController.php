<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordNotReused;
use App\Rules\PasswordPolicy;
use App\Services\Auth\AuthModeService;
use App\Services\Auth\PasswordLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        if (! AuthModeService::isLocalEnabled()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.local_disabled'),
            ], 403);
        }

        $email = strtolower(trim((string) $request->input('email')));

        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.failed'),
            ], 401);
        }

        // บัญชีจาก Entra/LDAP มีรหัสสุ่มใน DB — ให้ไป login ทาง SSO / LDAP
        if (in_array($user->auth_provider, ['entra', 'ldap'], true)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.directory_password_not_used'),
            ], 401);
        }

        $passwordHash = $user->getRawOriginal('password');

        if ($passwordHash === null || $passwordHash === '' || ! Hash::check($request->password, $passwordHash)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.failed'),
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => __('auth.account_deactivated'),
            ], 403);
        }

        if (AuthModeService::isLocalSuperAdminOnly() && ! $user->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => __('auth.local_super_admin_only'),
            ], 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.login_successful'),
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => null,
                'user' => $this->formatUser($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('auth.logout_successful'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatUser($request->user()),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', new PasswordPolicy, new PasswordNotReused($user)],
        ]);

        if (! Hash::check($request->current_password, $user->getRawOriginal('password'))) {
            return response()->json([
                'success' => false,
                'message' => __('auth.password'),
            ], 422);
        }

        PasswordLifecycleService::applySelfServicePasswordChange($user, $request->password);

        return response()->json([
            'success' => true,
            'message' => __('common.password_changed'),
            'data' => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'department' => $user->department?->name,
            'department_id' => $user->department_id,
            'org_unit_id' => $user->org_unit_id,
            'position' => $user->position?->name,
            'position_id' => $user->position_id,
            'is_active' => $user->is_active,
            'last_active_at' => $user->last_active_at?->toIso8601String(),
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'auth_provider' => $user->auth_provider,
            'is_super_admin' => (bool) $user->is_super_admin,
            'must_change_password' => PasswordLifecycleService::requiresPasswordChange($user),
        ];
    }
}
