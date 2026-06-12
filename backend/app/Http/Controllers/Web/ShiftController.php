<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShiftSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(): View
    {
        $shifts = Shift::query()->orderBy('code')->get();

        $assignments = UserShiftSchedule::query()
            ->with(['user:id,first_name,last_name,email', 'shift:id,code,name'])
            ->orderByDesc('effective_from')
            ->paginate(20);

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('settings.shifts.index', compact('shifts', 'assignments', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:shifts,code',
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:480',
            'color' => 'nullable|string|max:16',
        ]);

        Shift::create([...$validated, 'break_minutes' => (int) ($validated['break_minutes'] ?? 0)]);

        return redirect()->route('settings.shifts.index')->with('success', __('common.saved'));
    }

    public function toggleActive(Shift $shift): RedirectResponse
    {
        $shift->update(['is_active' => ! $shift->is_active]);

        return redirect()->back()->with('success', __('common.saved'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete(); // cascades to user_shift_schedules

        return redirect()->back()->with('success', __('common.deleted'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'work_days' => 'nullable|array',
            'work_days.*' => 'integer|between:1,7',
        ]);

        // Reject overlapping schedule windows for the same user — an open-ended
        // assignment (effective_to null) overlaps everything after its start.
        $from = $validated['effective_from'];
        $to = $validated['effective_to'] ?? null;
        $overlaps = UserShiftSchedule::query()
            ->where('user_id', $validated['user_id'])
            ->where(function ($q) use ($from, $to) {
                $q->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>=', $from));
                if ($to !== null) {
                    $q->where('effective_from', '<=', $to);
                }
            })
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages([
                'effective_from' => __('common.shift_assignment_overlap'),
            ]);
        }

        UserShiftSchedule::create($validated);

        return redirect()->route('settings.shifts.index')->with('success', __('common.saved'));
    }

    public function destroyAssignment(UserShiftSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->back()->with('success', __('common.deleted'));
    }
}
