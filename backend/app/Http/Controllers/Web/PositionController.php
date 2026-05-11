<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        $positions = Position::query()->orderBy('name')->get();

        return view('settings.positions.index', compact('positions'));
    }

    public function create(): View
    {
        return view('settings.positions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', 'unique:positions,code'],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        Position::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.positions.index')
            ->with('success', __('common.saved'));
    }

    public function edit(Position $position): View
    {
        return view('settings.positions.edit', compact('position'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('positions', 'code')->ignore($position->id)],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $position->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.positions.index')
            ->with('success', __('common.updated'));
    }

    public function destroy(Position $position): RedirectResponse
    {
        if (User::query()->where('position_id', $position->id)->exists()) {
            return redirect()->route('settings.positions.index')
                ->with('error', __('common.cannot_delete_position_has_users'));
        }

        $position->delete();

        return redirect()->route('settings.positions.index')->with('success', __('common.deleted'));
    }

    private function normalizeCode(mixed $raw): string
    {
        return strtoupper(trim((string) $raw));
    }
}
