<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\EquipmentLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentLocationController extends Controller
{
    use HasPerPage;

    public function browse(): View
    {
        $locations = EquipmentLocation::query()
            ->where('is_active', true)
            ->with(['equipment.category'])
            ->orderBy('name')
            ->get();

        return view('equipment-locations.index', compact('locations'));
    }

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'equipment_locations_per_page');
        $query = EquipmentLocation::query()->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%");
            });
        }

        $locations = $query->paginate($perPage)->withQueryString();

        return view('settings.equipment-locations.index', compact('locations', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.equipment-locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', 'unique:equipment_locations,code'],
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        EquipmentLocation::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'building' => $validated['building'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'zone' => $validated['zone'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('settings.equipment-locations.index')->with('success', __('common.saved'));
    }

    public function edit(EquipmentLocation $equipmentLocation): View
    {
        return view('settings.equipment-locations.edit', compact('equipmentLocation'));
    }

    public function update(Request $request, EquipmentLocation $equipmentLocation): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $equipmentLocation->update(['is_active' => ! $equipmentLocation->is_active]);
            return redirect()->route('settings.equipment-locations.index')->with('success', __('common.saved'));
        }

        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('equipment_locations', 'code')->ignore($equipmentLocation->id)],
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:100',
            'zone' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $equipmentLocation->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'building' => $validated['building'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'zone' => $validated['zone'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('settings.equipment-locations.index')->with('success', __('common.updated'));
    }

    public function destroy(EquipmentLocation $equipmentLocation): RedirectResponse
    {
        if ($equipmentLocation->equipment()->exists()) {
            return redirect()->route('settings.equipment-locations.index')
                ->with('error', __('common.cannot_delete_location_has_equipment'));
        }

        $equipmentLocation->delete();

        return redirect()->route('settings.equipment-locations.index')->with('success', __('common.deleted'));
    }

    private function normalizeCode(mixed $raw): string
    {
        return strtoupper(trim((string) $raw));
    }
}
