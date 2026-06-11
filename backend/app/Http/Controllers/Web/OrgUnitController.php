<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgUnitController extends Controller
{
    public function importForm(): View
    {
        return view('settings.org-units.import');
    }

    public function downloadTemplate()
    {
        $csv = "name,type,parent_name,head_email,sort_order\n";
        $csv .= "โรงเรียนตัวอย่าง,company,,,1\n";
        $csv .= "ฝ่ายวิชาการ,department,โรงเรียนตัวอย่าง,,1\n";
        $csv .= "ฝ่ายธุรการ,department,โรงเรียนตัวอย่าง,,2\n";

        return response()->streamDownload(
            fn () => print ($csv),
            'org_units_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('file')->getRealPath();
        $lines = array_map('str_getcsv', file($path));
        $header = array_shift($lines);

        $created = $updated = $skipped = 0;
        $errors = [];
        $nameToId = OrgUnit::pluck('id', 'name')->all();  // pre-seed from DB

        $validTypes = ['company', 'division', 'department', 'section', 'team'];

        foreach ($lines as $i => $row) {
            if (count($row) < 1 || empty(trim($row[0]))) {
                $skipped++;

                continue;
            }
            $data = array_combine($header, array_pad($row, count($header), null));
            $name = trim($data['name'] ?? '');
            $type = trim($data['type'] ?? 'department');
            $parentName = trim($data['parent_name'] ?? '');
            $headEmail = trim($data['head_email'] ?? '');
            $sortOrder = (int) ($data['sort_order'] ?? 0);

            if (empty($name)) {
                $skipped++;

                continue;
            }

            if (! in_array($type, $validTypes, true)) {
                $errors[] = 'Row '.($i + 2).": type \"{$type}\" ไม่ถูกต้อง (ใช้ company/division/department/section/team)";
                $skipped++;

                continue;
            }

            try {
                $parentId = $parentName !== '' ? ($nameToId[$parentName] ?? null) : null;
                $headId = $headEmail !== '' ? User::where('email', $headEmail)->value('id') : null;

                $existing = OrgUnit::where('name', $name)->first();
                if ($existing) {
                    $existing->update([
                        'type' => $type,
                        'parent_id' => $parentId,
                        'head_user_id' => $headId,
                        'sort_order' => $sortOrder,
                    ]);
                    $nameToId[$name] = $existing->id;
                    $updated++;
                } else {
                    $unit = OrgUnit::create([
                        'name' => $name,
                        'type' => $type,
                        'parent_id' => $parentId,
                        'head_user_id' => $headId,
                        'sort_order' => $sortOrder,
                        'is_active' => true,
                    ]);
                    $nameToId[$name] = $unit->id;
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Row '.($i + 2).': '.$e->getMessage();
            }
        }

        $message = __('common.import_result', compact('created', 'updated', 'skipped'));
        if (! empty($errors)) {
            return redirect()->route('settings.org-units.import')
                ->with('success', $message)
                ->with('import_errors', array_slice($errors, 0, 10));
        }

        return redirect()->route('settings.org-units.index')->with('success', $message);
    }

    public function index(): View
    {
        $roots = OrgUnit::with(['children.children.children', 'head'])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.org-units.index', compact('roots'));
    }

    public function treeJson(): JsonResponse
    {
        $units = OrgUnit::with('head')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $map = $units->keyBy('id');
        $roots = [];
        foreach ($units as $unit) {
            $unit->_children = [];
        }
        foreach ($units as $unit) {
            if ($unit->parent_id && $map->has($unit->parent_id)) {
                $map[$unit->parent_id]->_children[] = $unit;
            } else {
                $roots[] = $unit;
            }
        }

        return response()->json($roots);
    }

    public function create(): View
    {
        $allUnits = OrgUnit::orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $headCandidates = User::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();

        return view('settings.org-units.create', compact('allUnits', 'branches', 'headCandidates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:company,division,department,section,team',
            'parent_id' => 'nullable|exists:org_units,id',
            'head_user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        OrgUnit::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'head_user_id' => $validated['head_user_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('settings.org-units.index')
            ->with('success', __('common.org_unit_created'));
    }

    public function edit(OrgUnit $orgUnit): View
    {
        $allUnits = OrgUnit::where('id', '!=', $orgUnit->id)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $headCandidates = User::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
        $membersCount = $orgUnit->members()->count();

        return view('settings.org-units.edit', compact('orgUnit', 'allUnits', 'branches', 'headCandidates', 'membersCount'));
    }

    public function update(Request $request, OrgUnit $orgUnit): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $orgUnit->update(['is_active' => ! $orgUnit->is_active]);

            return redirect()->route('settings.org-units.index')->with('success', __('common.saved'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:company,division,department,section,team',
            'parent_id' => [
                'nullable',
                'exists:org_units,id',
                function ($attribute, $value, $fail) use ($orgUnit) {
                    if ($value && (int) $value === $orgUnit->id) {
                        $fail(__('common.org_unit_cannot_be_own_parent'));
                    }
                },
            ],
            'head_user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $orgUnit->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'head_user_id' => $validated['head_user_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? $orgUnit->is_active),
        ]);

        return redirect()->route('settings.org-units.edit', $orgUnit)
            ->with('success', __('common.updated'));
    }

    public function destroy(OrgUnit $orgUnit): RedirectResponse
    {
        if ($orgUnit->children()->exists()) {
            return redirect()->route('settings.org-units.index')
                ->with('error', __('common.org_unit_has_children'));
        }

        if ($orgUnit->members()->exists()) {
            return redirect()->route('settings.org-units.index')
                ->with('error', __('common.org_unit_has_members'));
        }

        $orgUnit->delete();

        return redirect()->route('settings.org-units.index')
            ->with('success', __('common.deleted'));
    }
}
