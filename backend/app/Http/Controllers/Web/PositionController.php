<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'positions_per_page');

        $positions = Position::query()
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.positions.index', compact('positions', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.positions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:positions,code',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        Position::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:100|unique:positions,code,{$position->id}",
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $position->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
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
}
