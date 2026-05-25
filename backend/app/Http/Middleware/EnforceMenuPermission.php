<?php

namespace App\Http\Middleware;

use App\Models\NavigationMenu;
use App\Models\User;
use App\Services\NavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMenuPermission
{
    public function __construct(private readonly NavigationService $navigation) {}

    /**
     * If the current request path is covered by a navigation menu that carries
     * a permission, enforce that permission on the route itself — so a menu's
     * `permission` gates the underlying page, not just the sidebar visibility.
     * Super-admins bypass. Runs after auth.web (the user is resolved by then).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // auth.web already redirects unauthenticated requests; super-admin bypasses every gate.
        if (! $user instanceof User || $user->is_super_admin) {
            return $next($request);
        }

        $path = $request->path();
        $granted = $user->getAllPermissions()->pluck('name')->all();

        foreach ($this->navigation->routePermissionMap() as $entry) {
            if (NavigationMenu::routeMatchesPath($entry['route'], $path)) {
                // routePermissionMap() is sorted longest-route-first → the first
                // hit is the most specific menu covering this path.
                abort_unless(in_array($entry['permission'], $granted, true), 403);

                break;
            }
        }

        return $next($request);
    }
}
