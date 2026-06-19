<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Models\ReportDashboard;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Resolve and render the user's home dashboard. The page is now backed by
     * a `ReportDashboard` (admin-built via `/settings/dashboards`) instead of
     * the previous hardcoded KPI grid — so adding/changing cards only needs
     * widget edits, no code change.
     *
     * Resolution order: per-user pick (`users.home_dashboard_id`) → global
     * default (`settings.default_home_dashboard_id`) → first
     * accessible-and-active dashboard. When no dashboard matches the user (no
     * accessible dashboards exist at all) we render a friendly empty state.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $dashboard = $this->resolveHomeDashboard($user);
        $availableDashboards = $this->accessibleDashboards($user);

        if (! $dashboard) {
            return view('dashboard-empty', [
                'user' => $user,
                'availableDashboards' => $availableDashboards,
            ]);
        }

        $dashboard->load('widgets');
        $apiToken = session('api_token');
        $orgUnits = OrgUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('dashboard', compact(
            'dashboard',
            'apiToken',
            'orgUnits',
            'availableDashboards',
            'user'
        ));
    }

    private function resolveHomeDashboard(?User $user): ?ReportDashboard
    {
        if ($user?->home_dashboard_id) {
            $picked = ReportDashboard::find($user->home_dashboard_id);
            if ($picked && $picked->canBeAccessedBy($user)) {
                return $picked;
            }
        }

        $defaultId = Setting::get('default_home_dashboard_id');
        if ($defaultId) {
            $default = ReportDashboard::find((int) $defaultId);
            if ($default && $default->canBeAccessedBy($user)) {
                return $default;
            }
        }

        return ReportDashboard::query()
            ->where('is_active', true)
            ->accessibleTo($user)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ReportDashboard>
     */
    private function accessibleDashboards(?User $user)
    {
        return ReportDashboard::query()
            ->where('is_active', true)
            ->accessibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
