<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->groupBy(fn (Holiday $h) => $h->date->format('m'));

        $years = Holiday::query()
            ->pluck('date')
            ->map(fn ($d) => (int) $d->format('Y'))
            ->push($year, (int) now()->year)
            ->unique()
            ->sort()
            ->values();

        return view('settings.holidays.index', compact('holidays', 'year', 'years'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string|max:255',
        ]);

        Holiday::create($validated);

        return redirect()
            ->route('settings.holidays.index', ['year' => substr($validated['date'], 0, 4)])
            ->with('success', __('common.saved'));
    }

    public function toggleActive(Holiday $holiday): RedirectResponse
    {
        $holiday->update(['is_active' => ! $holiday->is_active]);

        return redirect()->back()->with('success', __('common.saved'));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->back()->with('success', __('common.deleted'));
    }
}
