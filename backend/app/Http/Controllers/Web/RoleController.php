<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    use HasPerPage;

    /**
     * Read actions (index / show / overview) stay open to any authenticated
     * user — see SettingsMenuAccessTest. Write actions are super-admin only.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('super-admin', except: ['index', 'show', 'overview']),
        ];
    }

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'roles_per_page');
        $roles = Role::withCount('permissions')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at,
            ]);

        return view('roles.index', compact('roles', 'perPage'));
    }

    public function show(int $id): View
    {
        $role = Role::with('permissions')->findOrFail($id);
        $role->users_count = $role->users()->count();

        $permissionsByModule = [];
        foreach ($role->permissions as $perm) {
            $module = $this->moduleKeyForPermission($perm);
            $parts = explode('.', $perm->name, 2);
            $action = $parts[1] ?? $perm->name;
            $permissionsByModule[$module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'action' => $action,
            ];
        }

        $role = $role->toArray();
        $role['permissions_by_module'] = $permissionsByModule;

        return view('roles.show', compact('role'));
    }

    public function create(): View
    {
        $grouped = $this->groupedPermissions();

        return view('roles.create', compact('grouped'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return redirect()->route('roles.index')->with('success', __('common.role_flash_created'));
    }

    public function edit(int $id): View
    {
        $role = Role::with('permissions')->findOrFail($id)->toArray();
        $grouped = $this->groupedPermissions();

        return view('roles.edit', compact('role', 'grouped'));
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $request->validate(['name' => 'sometimes|string|unique:roles,name,'.$id]);

        if ($request->has('name')) {
            $role->update(['name' => $request->name]);
        }

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return redirect()->route('roles.index')->with('success', __('common.role_flash_updated'));
    }

    public function destroy(int $id)
    {
        Role::findOrFail($id)->delete();

        return redirect()->route('roles.index')->with('success', __('common.role_flash_deleted'));
    }

    /**
     * Read-only RBAC matrix — every permission (grouped by module) against
     * every role, so a super-admin can see at a glance who can do what.
     */
    public function overview(): View
    {
        $grouped = $this->groupedPermissions();
        $roles = Role::with('permissions:id,name')->orderBy('name')->get();
        $rolePermissionIds = $roles
            ->mapWithKeys(fn ($role) => [$role->id => $role->permissions->pluck('id')->all()])
            ->all();

        return view('roles.overview', compact('grouped', 'roles', 'rolePermissionIds'));
    }

    private function groupedPermissions(): array
    {
        $grouped = [];
        foreach (Permission::query()->orderBy('module')->orderBy('name')->get() as $perm) {
            $module = $this->moduleKeyForPermission($perm);
            $parts = explode('.', $perm->name, 2);
            $action = $parts[1] ?? $perm->name;
            $grouped[$module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'action' => $action,
            ];
        }

        return $grouped;
    }

    private function moduleKeyForPermission(Permission $permission): string
    {
        if (filled($permission->module)) {
            return (string) $permission->module;
        }

        $parts = explode('.', $permission->name, 2);

        return $parts[0] ?? 'other';
    }
}
