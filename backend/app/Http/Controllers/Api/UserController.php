<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PasswordNotReused;
use App\Rules\PasswordPolicy;
use App\Services\Auth\LdapUserCreateValidation;
use App\Services\Auth\PasswordLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        $query = User::with(['roles', 'company', 'branch']);

        // Search by first_name, last_name, or email
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by company_id
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        // Filter by branch_id
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['roles', 'permissions', 'company', 'branch'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'string', new PasswordPolicy],
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'position_id' => 'required|exists:positions,id',
            'phone' => 'nullable|string|max:255',
            'roles' => 'array',
        ]);

        LdapUserCreateValidation::assertEmailAllowedForLocalUserCreate($request->email);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'password_changed_at' => now(),
            'password_must_change' => Setting::getBool('password_force_change_first_login'),
            'company_id' => $request->company_id,
            'branch_id' => $request->branch_id,
            'position_id' => $request->position_id,
            'phone' => $request->phone,
        ]);

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return response()->json([
            'success' => true,
            'message' => __('users.user_created'),
            'data' => $user->load(['roles', 'company', 'branch']),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $rules = [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'branch_id' => 'sometimes|nullable|exists:branches,id',
            'position_id' => 'sometimes|nullable|exists:positions,id',
            'phone' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'roles' => 'array',
        ];
        if ($request->filled('password')) {
            $rules['password'] = ['string', new PasswordPolicy, new PasswordNotReused($user)];
        }
        $request->validate($rules);

        $data = $request->only([
            'first_name', 'last_name', 'email',
            'company_id', 'branch_id',
            'position_id', 'phone',
            'is_active',
        ]);

        $user->update($data);

        if ($request->filled('password')) {
            PasswordLifecycleService::applyAdminPasswordAssignment($user, $request->password);
        }

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return response()->json([
            'success' => true,
            'message' => __('users.user_updated'),
            'data' => $user->load(['roles', 'company', 'branch']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('users.user_deleted'),
        ]);
    }
}
