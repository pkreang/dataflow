<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller implements HasMiddleware
{
    /**
     * Listing permissions stays open to any authenticated user (see
     * SettingsMenuAccessTest); creating / editing / deleting is super-admin only.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('super-admin', except: ['index']),
        ];
    }

    public function index(): View
    {
        $permissions = Permission::orderBy('name')->get();

        $grouped = [];
        foreach ($permissions as $perm) {
            $module = $this->moduleKeyForPermission($perm);
            $parts = explode('.', $perm->name, 2);
            $action = $parts[1] ?? $perm->name;
            $grouped[$module][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'action' => $action,
                'in_use' => $this->permissionInUse($perm),
            ];
        }

        $total = $permissions->count();

        return view('permissions.index', compact('grouped', 'total'));
    }

    public function create(): View
    {
        return view('permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:100|unique:permissions,name',
            ],
            [],
            [
                'name' => __('common.permission_name'),
            ]
        );

        $parts = explode('.', $validated['name'], 2);
        Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'module' => $parts[0],
            'action' => $parts[1] ?? $parts[0],
        ]);

        $this->forgetPermissionCache();

        return redirect()->route('permissions.index')->with('success', __('common.saved'));
    }

    public function edit(Permission $permission): View
    {
        return view('permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:100|unique:permissions,name,'.$permission->id,
            ],
            [],
            [
                'name' => __('common.permission_name'),
            ]
        );

        $parts = explode('.', $validated['name'], 2);
        $permission->update([
            'name' => $validated['name'],
            'module' => $parts[0],
            'action' => $parts[1] ?? $parts[0],
        ]);

        $this->forgetPermissionCache();

        return redirect()->route('permissions.index')->with('success', __('common.saved'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->permissionInUse($permission)) {
            return redirect()->route('permissions.index')->with('error', __('common.permission_cannot_delete_in_use'));
        }

        $permission->delete();
        $this->forgetPermissionCache();

        return redirect()->route('permissions.index')->with('success', __('common.permission_deleted'));
    }

    private function moduleKeyForPermission(Permission $permission): string
    {
        if (filled($permission->module)) {
            return (string) $permission->module;
        }

        $parts = explode('.', $permission->name, 2);

        return $parts[0] ?? 'other';
    }

    private function permissionInUse(Permission $permission): bool
    {
        if ($permission->roles()->exists()) {
            return true;
        }

        $table = config('permission.table_names.model_has_permissions');

        return DB::table($table)->where('permission_id', $permission->id)->exists();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
