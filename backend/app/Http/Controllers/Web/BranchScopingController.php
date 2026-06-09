<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchScopingController extends Controller
{
    private const KEYS = [
        'branches.enabled',
        'branch_scoping.enabled',
    ];

    public function edit(): View
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('settings.branch-scoping', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $toggles = $request->input('toggle', []);

        foreach (self::KEYS as $key) {
            Setting::set(
                $key,
                ($toggles[$key] ?? '0') === '1' ? '1' : '0'
            );
        }

        return redirect()
            ->route('settings.branch-scoping')
            ->with('success', __('common.saved'));
    }
}
