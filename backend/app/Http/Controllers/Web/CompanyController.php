<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Support\BranchesSetting;
use App\Support\StructuredAddressValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $query = Company::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $this->resolvePerPage($request, 'companies_per_page');
        $companies = $query->orderBy('name')->withCount('branches')->paginate($perPage)->withQueryString();

        $mode = Setting::get('company_mode', 'single');
        $canCreateMore = $mode === 'multi' || Company::count() === 0;

        return view('companies.index', compact('companies', 'canCreateMore', 'perPage'));
    }

    public function create(): View|RedirectResponse
    {
        $mode = Setting::get('company_mode', 'single');
        if ($mode === 'single' && Company::count() > 0) {
            return redirect()->route('companies.index')
                ->with('error', __('company.single_mode_limit'));
        }

        return view('companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $mode = Setting::get('company_mode', 'single');
        if ($mode === 'single' && Company::count() > 0) {
            return redirect()->route('companies.index')
                ->with('error', __('company.single_mode_limit'));
        }

        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code',
            'tax_id' => 'nullable|string|max:20',
            'business_type' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'website' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'boolean',
        ], StructuredAddressValidation::rules()), [], array_merge([
            'name' => __('company.company_name'),
            'code' => __('company.company_code'),
            'tax_id' => __('company.tax_id'),
            'business_type' => __('company.business_type'),
            'email' => __('common.email'),
            'phone' => __('company.phone'),
            'fax' => __('company.fax'),
            'website' => __('company.website'),
            'description' => __('common.description'),
            'logo' => __('company.logo'),
        ], StructuredAddressValidation::attributeNames()));

        $validated['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('companies', 'public');
        }

        Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', __('company.company_created'));
    }

    public function show(Company $company): RedirectResponse
    {
        return redirect()->route('companies.edit', $company);
    }

    public function edit(Company $company): View
    {
        $company->load(['branches' => fn ($q) => $q->orderBy('code')]);
        $branchesManagementEnabled = BranchesSetting::managementEnabled();

        return view('companies.edit', compact('company', 'branchesManagementEnabled'));
    }

    public function storeBranch(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()?->can('manage profile'), 403);
        if (! BranchesSetting::managementEnabled()) {
            return redirect()
                ->route('companies.edit', $company)
                ->with('error', __('company.branches_management_disabled'));
        }

        $validated = $request->validate(array_merge([
            'branch_name' => 'required|string|max:255',
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->where('company_id', $company->id),
            ],
            'branch_phone' => 'nullable|string|max:20',
            'branch_is_active' => 'nullable|boolean',
        ], StructuredAddressValidation::rulesForBranchFormPrefix()), [], array_merge([
            'branch_name' => __('company.branch_name'),
            'branch_code' => __('company.branch_code'),
            'branch_phone' => __('company.branch_phone'),
        ], StructuredAddressValidation::attributeNamesForBranchFormPrefix()));

        $company->branches()->create(array_merge(
            [
                'name' => $validated['branch_name'],
                'code' => $validated['branch_code'],
                'phone' => $validated['branch_phone'] ?? null,
                'is_active' => $request->boolean('branch_is_active', true),
            ],
            StructuredAddressValidation::onlyStructuredFromBranchPrefixed($validated),
        ));

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', __('company.branch_created'));
    }

    public function updateBranch(Request $request, Company $company, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->can('manage profile'), 403);
        abort_unless($branch->company_id === $company->id, 404);
        if (! BranchesSetting::managementEnabled()) {
            return redirect()
                ->route('companies.edit', $company)
                ->with('error', __('company.branches_management_disabled'));
        }

        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')
                    ->where(fn ($q) => $q->where('company_id', $company->id))
                    ->ignore($branch->id),
            ],
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ], StructuredAddressValidation::rules()), [], array_merge([
            'name' => __('company.branch_name'),
            'code' => __('company.branch_code'),
            'phone' => __('company.branch_phone'),
        ], StructuredAddressValidation::attributeNames()));

        $branch->update(array_merge(
            [
                'name' => $validated['name'],
                'code' => $validated['code'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ],
            array_intersect_key($validated, array_flip(Company::structuredAddressAttributes())),
        ));

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', __('company.branch_updated'));
    }

    public function destroyBranch(Request $request, Company $company, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->can('manage profile'), 403);
        abort_unless($branch->company_id === $company->id, 404);
        if (! BranchesSetting::managementEnabled()) {
            return redirect()
                ->route('companies.edit', $company)
                ->with('error', __('company.branches_management_disabled'));
        }

        if (User::query()->where('branch_id', $branch->id)->exists()) {
            return redirect()
                ->route('companies.edit', $company)
                ->with('error', __('company.cannot_delete_branch_has_users'));
        }

        $branch->delete();

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', __('company.branch_deleted'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $company->update(['is_active' => ! $company->is_active]);
            return redirect()->route('companies.index')->with('success', __('common.saved'));
        }

        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code,'.$company->id,
            'tax_id' => 'nullable|string|max:20',
            'business_type' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'website' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'boolean',
        ], StructuredAddressValidation::rules()), [], array_merge([
            'name' => __('company.company_name'),
            'code' => __('company.company_code'),
            'tax_id' => __('company.tax_id'),
            'business_type' => __('company.business_type'),
            'email' => __('common.email'),
            'phone' => __('company.phone'),
            'fax' => __('company.fax'),
            'website' => __('company.website'),
            'description' => __('common.description'),
            'logo' => __('company.logo'),
        ], StructuredAddressValidation::attributeNames()));

        $validated['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $validated['logo'] = $request->file('logo')->store('companies', 'public');
        }

        $company->update($validated);

        return redirect()->route('companies.edit', $company)
            ->with('success', __('company.company_updated'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->branches()->exists()) {
            return redirect()->route('companies.index')
                ->with('error', __('company.cannot_delete_has_branches'));
        }

        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', __('company.company_deleted'));
    }
}
