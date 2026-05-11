<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Services\BranchScopeService;
use App\Support\BranchesSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EquipmentRegistryController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $query = Equipment::with(['category', 'location', 'company']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('equipment_category_id', $categoryId);
        }

        if ($locationId = $request->input('location_id')) {
            $query->where('equipment_location_id', $locationId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        BranchScopeService::constrainEquipmentQuery($query, Auth::user());

        $perPage = $this->resolvePerPage($request, 'equipment_registry_per_page');
        $equipment = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $categories = EquipmentCategory::where('is_active', true)->orderBy('name')->get();
        $locations = EquipmentLocation::where('is_active', true)->orderBy('name')->get();

        return view('equipment-registry.index', compact('equipment', 'categories', 'locations', 'perPage'));
    }

    public function create(): View
    {
        $categories = EquipmentCategory::where('is_active', true)->orderBy('name')->get();
        $locations = EquipmentLocation::where('is_active', true)->orderBy('name')->get();
        $companies = Company::where('is_active', true)->with('branches')->orderBy('name')->get();
        $branchesManagementEnabled = BranchesSetting::managementEnabled();

        return view('equipment-registry.create', compact('categories', 'locations', 'companies', 'branchesManagementEnabled'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:equipment,code',
            'serial_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'equipment_category_id' => 'required|exists:equipment_categories,id',
            'equipment_location_id' => 'required|exists:equipment_locations,id',
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'required|in:active,inactive,under_maintenance,decommissioned',
            'criticality' => 'nullable|in:A,B,C',
            'installed_date' => 'nullable|date',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date',
            'runtime_hours' => 'nullable|numeric|min:0|max:9999999.99',
            'specifications' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $branchesManagementEnabled = BranchesSetting::managementEnabled();
        if ($branchesManagementEnabled
            && ! BranchScopeService::submittedBranchIdValid($user, BranchScopeService::MODULE_EQUIPMENT, $validated['branch_id'] ?? null)) {
            return back()->withErrors(['branch_id' => __('validation.in', ['attribute' => 'branch'])])->withInput();
        }
        if ($branchesManagementEnabled) {
            $companyId = $validated['company_id'] ?? null;
            $branchId = $validated['branch_id'] ?? null;
        } else {
            $companyId = $user->company_id;
            $branchId = $user->branch_id;
        }
        if ($branchId === null) {
            $branchId = BranchScopeService::defaultBranchIdForUser($user, BranchScopeService::MODULE_EQUIPMENT);
        }

        Equipment::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'equipment_category_id' => $validated['equipment_category_id'],
            'equipment_location_id' => $validated['equipment_location_id'],
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'status' => $validated['status'],
            'criticality' => $validated['criticality'] ?? null,
            'installed_date' => $validated['installed_date'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_expiry' => $validated['warranty_expiry'] ?? null,
            'runtime_hours' => $validated['runtime_hours'] ?? null,
            'specifications' => $validated['specifications'] ? json_decode($validated['specifications'], true) : null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('equipment-registry.index')->with('success', __('common.saved'));
    }

    public function edit(Equipment $equipment): View
    {
        abort_unless(BranchScopeService::userCanAccessEquipment(Auth::user(), $equipment), 403);

        $categories = EquipmentCategory::where('is_active', true)->orderBy('name')->get();
        $locations = EquipmentLocation::where('is_active', true)->orderBy('name')->get();
        $companies = Company::where('is_active', true)->with('branches')->orderBy('name')->get();
        $branchesManagementEnabled = BranchesSetting::managementEnabled();

        return view('equipment-registry.edit', compact('equipment', 'categories', 'locations', 'companies', 'branchesManagementEnabled'));
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless(BranchScopeService::userCanAccessEquipment(Auth::user(), $equipment), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:100|unique:equipment,code,{$equipment->id}",
            'serial_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'equipment_category_id' => 'required|exists:equipment_categories,id',
            'equipment_location_id' => 'required|exists:equipment_locations,id',
            'company_id' => 'nullable|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'required|in:active,inactive,under_maintenance,decommissioned',
            'criticality' => 'nullable|in:A,B,C',
            'installed_date' => 'nullable|date',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date',
            'runtime_hours' => 'nullable|numeric|min:0|max:9999999.99',
            'specifications' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $branchesManagementEnabled = BranchesSetting::managementEnabled();
        if ($branchesManagementEnabled
            && ! BranchScopeService::submittedBranchIdValid($user, BranchScopeService::MODULE_EQUIPMENT, $validated['branch_id'] ?? null)) {
            return back()->withErrors(['branch_id' => __('validation.in', ['attribute' => 'branch'])])->withInput();
        }
        if ($branchesManagementEnabled) {
            $companyId = $validated['company_id'] ?? null;
            $branchId = $validated['branch_id'] ?? null;
            if ($branchId === null) {
                $branchId = BranchScopeService::defaultBranchIdForUser($user, BranchScopeService::MODULE_EQUIPMENT);
            }
        } else {
            $companyId = $equipment->company_id;
            $branchId = $equipment->branch_id;
        }

        $equipment->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'serial_number' => $validated['serial_number'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'model' => $validated['model'] ?? null,
            'equipment_category_id' => $validated['equipment_category_id'],
            'equipment_location_id' => $validated['equipment_location_id'],
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'status' => $validated['status'],
            'criticality' => $validated['criticality'] ?? null,
            'installed_date' => $validated['installed_date'] ?? null,
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_expiry' => $validated['warranty_expiry'] ?? null,
            'runtime_hours' => $validated['runtime_hours'] ?? null,
            'specifications' => $validated['specifications'] ? json_decode($validated['specifications'], true) : null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('equipment-registry.index')->with('success', __('common.updated'));
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        abort_unless(BranchScopeService::userCanAccessEquipment(Auth::user(), $equipment), 403);

        $equipment->delete();

        return redirect()->route('equipment-registry.index')->with('success', __('common.deleted'));
    }
}
