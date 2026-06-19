<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReportDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $dashboards = ReportDashboard::withCount('widgets')
            ->where('is_active', true)
            ->accessibleTo($user)
            ->orderBy('created_at')
            ->get();

        return view('reports.index', compact('dashboards'));
    }

    public function showDashboard(ReportDashboard $dashboard, Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if (! $dashboard->canBeAccessedBy($user)) {
            if (! $dashboard->is_active) {
                abort(404);
            }
            if (! $user) {
                return redirect()->route('login');
            }
            abort(403);
        }

        $dashboard->load('widgets');

        $apiToken = session('api_token');
        $orgUnits = \App\Models\OrgUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('reports.dashboards.show', compact('dashboard', 'apiToken', 'orgUnits'));
    }
}
