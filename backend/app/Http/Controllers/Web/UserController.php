<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\LdapUserCreateValidation;
use App\Services\Auth\LdapUserDirectoryLookup;
use App\Services\Auth\PasswordCapabilityService;
use App\Support\CompliantPasswordGenerator;
use App\Support\PermissionDisplay;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    use HasPerPage;

    /**
     * Listing users stays open to any authenticated user (see
     * SettingsMenuAccessTest); creating / editing / importing / deleting
     * users is super-admin only.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('super-admin', except: ['index', 'show']),
        ];
    }

    public function index(Request $request): View
    {
        $query = User::with(['roles', 'jobPosition', 'department']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = $this->resolvePerPage($request, 'users_per_page');

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();
        $totalUsers = User::count();

        return view('users.index', compact('users', 'totalUsers', 'perPage'));
    }

    public function importForm(): View
    {
        return view('users.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ], [
            'file.required' => __('users.import_file_required'),
            'file.mimes' => __('users.import_file_mimes'),
        ]);

        if (LdapUserDirectoryLookup::userCreateValidationRequired()
            && ! LdapUserDirectoryLookup::isReadyForLookup()) {
            return redirect()
                ->route('users.import')
                ->withErrors(['file' => __('users.import_ldap_validation_unavailable')]);
        }

        $file = $request->file('file');
        $path = $file->getRealPath();
        $rows = array_map('str_getcsv', file($path));
        $header = array_shift($rows);

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (count($row) < 3) {
                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), null));
            $email = trim($data['email'] ?? $data['อีเมล'] ?? '');
            if (empty($email)) {
                $skipped++;

                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skipped++;
                $errors[] = __('users.import_skip_duplicate', ['email' => $email]);

                continue;
            }
            $firstName = trim($data['first_name'] ?? $data['ชื่อ'] ?? $data['name'] ?? '');
            $lastName = trim($data['last_name'] ?? $data['นามสกุล'] ?? '');
            if (empty($firstName) && empty($lastName)) {
                $firstName = explode('@', $email)[0];
            }
            if (LdapUserDirectoryLookup::userCreateValidationRequired()) {
                $ldapResult = LdapUserDirectoryLookup::searchByEmail($email);
                if ($ldapResult['type'] === LdapUserDirectoryLookup::TYPE_ERROR) {
                    $skipped++;
                    $errors[] = __('users.import_ldap_lookup_failed', ['email' => $email]);

                    continue;
                }
                if ($ldapResult['type'] === LdapUserDirectoryLookup::TYPE_NOT_FOUND) {
                    $skipped++;
                    $errors[] = __('users.import_email_not_in_ldap', ['email' => $email]);

                    continue;
                }
            }
            try {
                $deptName = trim($data['department'] ?? $data['แผนก'] ?? '');
                $positionName = trim($data['position'] ?? $data['ตำแหน่ง'] ?? '');
                $deptId = $deptName !== '' ? Department::where('name', $deptName)->value('id') : null;
                $posId = $positionName !== '' ? Position::where('name', $positionName)->value('id') : null;

                User::create([
                    'first_name' => $firstName ?: '-',
                    'last_name' => $lastName ?: '-',
                    'email' => $email,
                    'password' => CompliantPasswordGenerator::generate(),
                    'password_changed_at' => now(),
                    'password_must_change' => Setting::getBool('password_force_change_first_login'),
                    'department_id' => $deptId,
                    'position_id' => $posId,
                    'phone' => trim($data['phone'] ?? $data['เบอร์โทร'] ?? '') ?: null,
                    'remark' => trim($data['remark'] ?? $data['หมายเหตุ'] ?? '') ?: null,
                    'is_active' => true,
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        $message = __('users.import_result', ['created' => $created, 'skipped' => $skipped]);
        if (! empty($errors)) {
            $message .= ' '.__('users.import_errors', ['count' => count($errors)]);

            return redirect()->route('users.import')->with('success', $message)->with('import_errors', array_slice($errors, 0, 10));
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();
        $permGrid = $this->buildPermissionMatrix();
        $positions = Position::query()->where('is_active', true)->orderBy('name')->get();
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();

        return view('users.create', [
            'roles' => $roles,
            'permissionMatrix' => $permGrid['matrix'],
            'permissionActions' => $permGrid['actions'],
            'permissionActionLabels' => $permGrid['action_labels'],
            'positions' => $positions,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'phone' => 'nullable|string|max:50',
            'remark' => 'nullable|string|max:1000',
            'role_id' => 'required_if:role_type,default',
            'permissions' => 'required_if:role_type,custom|array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'email.unique' => __('users.validation_email_unique'),
            'role_id.required_if' => __('users.validation_role_required'),
            'permissions.required_if' => __('users.validation_permissions_required'),
        ]);

        LdapUserCreateValidation::assertEmailAllowedForLocalUserCreate($request->email);

        $position = Position::labelsForUser($request->input('position_id'));

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => CompliantPasswordGenerator::generate(),
            'password_changed_at' => now(),
            'password_must_change' => Setting::getBool('password_force_change_first_login'),
            'department_id' => $request->department_id,
            'position_id' => $position['id'],
            'phone' => $request->phone,
            'remark' => $request->remark,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $roleType = $request->input('role_type', 'default');

        if ($roleType === 'default' && $request->filled('role_id')) {
            $role = Role::find($request->role_id);
            if ($role) {
                $user->assignRole($role);
            }
        } elseif ($roleType === 'custom') {
            $permissionIds = (array) $request->input('permissions', []);
            $permissions = ! empty($permissionIds)
                ? Permission::whereIn('id', $permissionIds)->pluck('name')
                : collect();
            $user->syncPermissions($permissions);
        }

        return redirect()->route('users.index')->with('success', __('users.user_created'));
    }

    public function show(int $id): View
    {
        $user = User::with('roles', 'permissions')->findOrFail($id);

        return view('users.show', compact('user'));
    }

    public function edit(int $id): View
    {
        $user = User::with('roles', 'permissions')->findOrFail($id);
        $roles = Role::orderBy('name')->get();
        $permGrid = $this->buildPermissionMatrix();
        $positions = Position::query()
            ->where(function ($q) use ($user) {
                $q->where('is_active', true);
                if ($user->position_id) {
                    $q->orWhere('id', $user->position_id);
                }
            })
            ->orderBy('name')
            ->get();
        $departments = Department::query()
            ->where(function ($q) use ($user) {
                $q->where('is_active', true);
                if ($user->department_id) {
                    $q->orWhere('id', $user->department_id);
                }
            })
            ->orderBy('name')
            ->get();

        $canEditEmail = PasswordCapabilityService::canEditEmailInApp($user);

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'permissionMatrix' => $permGrid['matrix'],
            'permissionActions' => $permGrid['actions'],
            'permissionActionLabels' => $permGrid['action_labels'],
            'positions' => $positions,
            'departments' => $departments,
            'canEditEmail' => $canEditEmail,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($request->has('toggle_active')) {
            $user->update(['is_active' => ! $user->is_active]);
            $status = $user->is_active ? 'enabled' : 'disabled';

            return redirect()->route('users.index')->with('success', __("users.user_{$status}"));
        }

        $canEditEmail = PasswordCapabilityService::canEditEmailInApp($user);

        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'phone' => 'nullable|string|max:50',
            'remark' => 'nullable|string|max:1000',
        ];
        if ($canEditEmail) {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }
        $request->validate($rules);

        $payload = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'department_id' => $request->department_id,
            'position_id' => $request->position_id,
            'phone' => $request->phone,
            'remark' => $request->remark,
            'is_active' => $request->boolean('is_active', true),
        ];
        if ($canEditEmail) {
            $payload['email'] = $request->email;
        }
        $user->update($payload);

        if ((int) (session('user')['id'] ?? 0) === (int) $user->id) {
            $user->refresh();
            $sessionUser = session('user', []);
            $sessionUser['first_name'] = $user->first_name;
            $sessionUser['last_name'] = $user->last_name;
            $sessionUser['name'] = $user->full_name;
            $sessionUser['email'] = $user->email;
            session(['user' => $sessionUser]);
        }

        $roleType = $request->input('role_type', 'default');
        if ($roleType === 'default' && $request->filled('role_id')) {
            $user->syncRoles([]);
            $role = Role::find($request->role_id);
            if ($role) {
                $user->assignRole($role);
            }
            $user->syncPermissions([]);
        } elseif ($roleType === 'custom') {
            $user->syncRoles([]);
            $permissionIds = (array) $request->input('permissions', []);
            $permissions = ! empty($permissionIds)
                ? Permission::whereIn('id', $permissionIds)->pluck('name')
                : collect();
            $user->syncPermissions($permissions);
        }

        return redirect()->route('users.index')->with('success', __('common.updated'));
    }

    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->is_super_admin) {
            return redirect()->route('users.index')->with('error', __('users.cannot_delete_super_admin'));
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', __('users.user_deleted'));
    }

    /**
     * @return array{matrix: array<int, array{module: string, label: string, actions: array<string, int|null>}>, actions: array<int, string>, action_labels: array<string, string>}
     */
    private function buildPermissionMatrix(): array
    {
        $allPerms = Permission::query()->orderBy('module')->orderBy('name')->get();

        $uniqueActions = $allPerms
            ->map(function (Permission $perm) {
                if (filled($perm->action)) {
                    return (string) $perm->action;
                }
                $parts = explode('.', $perm->name, 2);

                return $parts[1] ?? '';
            })
            ->filter(fn (string $a) => $a !== '')
            ->unique()
            ->values()
            ->all();

        $preferred = ['create', 'read', 'update', 'delete', 'export', 'manage', 'approve', 'requisition', 'manage_own'];
        $orderedActions = [];
        foreach ($preferred as $p) {
            if (in_array($p, $uniqueActions, true)) {
                $orderedActions[] = $p;
            }
        }
        $rest = array_values(array_diff($uniqueActions, $orderedActions));
        sort($rest);
        $orderedActions = array_merge($orderedActions, $rest);

        $grouped = [];
        foreach ($allPerms as $perm) {
            $module = filled($perm->module) ? (string) $perm->module : (explode('.', $perm->name, 2)[0] ?? 'other');
            $action = filled($perm->action) ? (string) $perm->action : (explode('.', $perm->name, 2)[1] ?? '');
            if ($action === '') {
                continue;
            }
            $grouped[$module][$action] = $perm->id;
        }
        ksort($grouped);

        $matrix = [];
        foreach ($grouped as $module => $moduleActions) {
            $row = [
                'module' => $module,
                'label' => PermissionDisplay::module($module),
                'actions' => [],
            ];
            foreach ($orderedActions as $action) {
                $row['actions'][$action] = $moduleActions[$action] ?? null;
            }
            $matrix[] = $row;
        }

        $actionLabels = [];
        foreach ($orderedActions as $action) {
            $actionLabels[$action] = PermissionDisplay::action($action);
        }

        return [
            'matrix' => $matrix,
            'actions' => $orderedActions,
            'action_labels' => $actionLabels,
        ];
    }
}
