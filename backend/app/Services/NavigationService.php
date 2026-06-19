<?php

namespace App\Services;

use App\Models\DocumentForm;
use App\Models\NavigationMenu;
use App\Models\UserPinnedMenu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NavigationService
{
    /**
     * Build the menu tree filtered by the current user's permissions + department.
     * Form-linked menu rows (document_form_id not null) honor department visibility
     * at render time; everything else uses static permission/super-admin rules.
     */
    public function getMenus(array $permissions, bool $isSuperAdmin, ?int $userDepartmentId = null, ?int $userOrgUnitId = null): Collection
    {
        $tree = Cache::remember('navigation_menus_tree', 3600, function () {
            return NavigationMenu::rootMenus()->with('children')->get();
        });

        return $tree
            ->map(fn ($menu) => clone $menu)
            ->filter(fn ($menu) => $this->isAccessible($menu, $permissions, $isSuperAdmin, $userDepartmentId, $userOrgUnitId))
            ->map(function ($menu) use ($permissions, $isSuperAdmin, $userDepartmentId, $userOrgUnitId) {
                if ($menu->children->isNotEmpty()) {
                    $filtered = $menu->children
                        ->map(fn ($c) => clone $c)
                        ->filter(fn ($child) => $this->isAccessible($child, $permissions, $isSuperAdmin, $userDepartmentId, $userOrgUnitId));
                    $menu->setRelation('children', $filtered);
                }

                return $menu;
            })
            ->filter(function ($menu) {
                // Hide group-only menus whose children were all filtered out.
                if ($menu->route === null && $menu->children->isEmpty()) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * Resolve the user's pinned menu items into full menu objects the sidebar can
     * render. Pinned menus surface above the main tree so frequently-used pages
     * stay one click away regardless of how many children the user has.
     */
    public function getPinnedMenus(int $userId, Collection $allMenus): Collection
    {
        $keys = UserPinnedMenu::keysFor($userId);
        if (empty($keys)) {
            return collect();
        }

        $lookup = collect();
        $flatten = function ($menus) use (&$lookup, &$flatten) {
            foreach ($menus as $m) {
                if ($m->id > 0) {
                    $lookup->put((string) $m->id, $m);
                }
                if ($m->children && $m->children->isNotEmpty()) {
                    $flatten($m->children);
                }
            }
        };
        $flatten($allMenus);

        return collect($keys)
            ->map(fn ($k) => $lookup->get($k))
            ->filter()
            ->values();
    }

    /**
     * Flat list of active menus that carry BOTH a route and a permission —
     * consumed by EnforceMenuPermission to gate routes. Sorted longest-route
     * first so the caller can take the most specific match. Cached; the cache
     * is cleared alongside the menu tree on any NavigationMenu save/delete.
     *
     * @return list<array{route: string, permission: string}>
     */
    public function routePermissionMap(): array
    {
        return Cache::remember('navigation_route_permissions', 3600, function () {
            return NavigationMenu::query()
                ->where('is_active', true)
                ->whereNotNull('route')->where('route', '!=', '')
                ->whereNotNull('permission')->where('permission', '!=', '')
                ->orderByRaw('LENGTH(route) DESC')
                ->get(['route', 'permission'])
                ->map(fn ($m) => ['route' => (string) $m->route, 'permission' => (string) $m->permission])
                ->all();
        });
    }

    private function isAccessible(NavigationMenu $menu, array $permissions, bool $isSuperAdmin, ?int $userDepartmentId = null, ?int $userOrgUnitId = null): bool
    {
        if ($this->menuRouteRequiresInstanceSuperAdmin($menu->route)) {
            return $isSuperAdmin;
        }

        // Form-linked menu rows respect org_unit/department visibility in addition
        // to the menu's own is_active flag. Super-admin sees every form.
        if ($menu->document_form_id && ! $isSuperAdmin) {
            if (! $this->menuVisibleForDocumentForm((int) $menu->document_form_id, $userDepartmentId, $userOrgUnitId)) {
                return false;
            }
        }

        if ($menu->permission === null) {
            return true;
        }
        if ($isSuperAdmin) {
            return true;
        }

        return in_array($menu->permission, $permissions);
    }

    /**
     * Routes under /settings/ use middleware super-admin (DB is_super_admin), except password policy.
     */
    private function menuRouteRequiresInstanceSuperAdmin(?string $route): bool
    {
        if ($route === null || $route === '') {
            return false;
        }

        $path = '/'.ltrim($route, '/');
        if ($path === '/settings/password-policy') {
            return false;
        }

        return str_starts_with($path, '/settings/');
    }

    /**
     * Cached per-request lookup: does the form's department binding admit the
     * given user department? Keeps the sidebar snappy when a user has many
     * form-linked rows.
     */
    private function menuVisibleForDocumentForm(int $formId, ?int $userDepartmentId, ?int $userOrgUnitId = null): bool
    {
        static $cache = [];
        $key = $formId.'|'.($userOrgUnitId ?? 'null').'|'.($userDepartmentId ?? 'null');
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $cache[$key] = DocumentForm::query()
            ->whereKey($formId)
            ->visibleToUser($userOrgUnitId, $userDepartmentId)
            ->exists();
    }
}
