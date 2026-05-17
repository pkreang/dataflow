<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HasPerPage;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\DepartmentWorkflowBinding;
use App\Models\DocumentType;
use App\Support\WorkflowDocumentTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'departments_per_page');
        $departments = Department::query()
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.departments.index', compact('departments', 'perPage'));
    }

    public function create(): View
    {
        return view('settings.departments.create');
    }

    public function edit(Department $department): View
    {
        $workflows = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $department->load(['workflowBindings.workflow']);
        $documentTypes = WorkflowDocumentTypes::forBindings();

        return view('settings.departments.edit', compact('department', 'workflows', 'documentTypes'));
    }

    public function workflowBindingsMatrix(): View
    {
        $departments = Department::query()->orderBy('name')->get();
        $documentTypes = WorkflowDocumentTypes::forBindings();
        $workflows = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments->load(['workflowBindings.workflow']);

        // Build label map from DocumentType master table (locale-aware)
        $documentTypeLabels = DocumentType::allActive()
            ->pluck('code')
            ->mapWithKeys(fn ($code) => [
                $code => DocumentType::allActive()->firstWhere('code', $code)?->label() ?? $code,
            ])
            ->toArray();

        // Build initial bindings map for Alpine.js: "deptId|docType" => "workflowId"
        $initialBindings = [];
        foreach ($departments as $dept) {
            foreach ($documentTypes as $docType) {
                $key = $dept->id . '|' . $docType;
                $binding = $dept->workflowBindings->firstWhere('document_type', $docType);
                $initialBindings[$key] = $binding ? (string) $binding->workflow_id : '';
            }
        }

        return view('settings.departments.workflow-bindings-matrix', compact(
            'departments',
            'documentTypes',
            'workflows',
            'documentTypeLabels',
            'initialBindings'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', 'unique:departments,code'],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.departments.edit', $department)
            ->with('success', __('common.department_created_bind_workflows'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $request->merge(['code' => $this->normalizeCode($request->input('code'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('departments', 'code')->ignore($department->id)],
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $department->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('settings.departments.edit', $department)
            ->with('success', __('common.updated'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->workflowBindings()->exists() || \App\Models\User::where('department', $department->name)->exists()) {
            return redirect()->route('settings.departments.index')
                ->with('error', __('common.cannot_delete_department'));
        }

        $department->delete();

        return redirect()->route('settings.departments.index')->with('success', __('common.deleted'));
    }

    public function bulkBindWorkflows(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bindings' => 'required|array|min:1',
            'bindings.*.department_id' => 'required|exists:departments,id',
            'bindings.*.document_type' => 'required|string|max:50',
            'bindings.*.workflow_id' => 'nullable',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['bindings'] as $entry) {
                $deptId = (int) $entry['department_id'];
                $docType = $entry['document_type'];
                $workflowId = $entry['workflow_id'] ?? '';

                if ($workflowId === '' || $workflowId === null) {
                    DepartmentWorkflowBinding::where('department_id', $deptId)
                        ->where('document_type', $docType)
                        ->delete();
                } else {
                    $workflow = ApprovalWorkflow::findOrFail((int) $workflowId);
                    if ($workflow->document_type !== $docType) {
                        continue;
                    }
                    DepartmentWorkflowBinding::updateOrCreate(
                        ['department_id' => $deptId, 'document_type' => $docType],
                        ['workflow_id' => (int) $workflowId]
                    );
                }
            }
        });

        return redirect()
            ->route('settings.department-workflow-bindings.index')
            ->with('success', __('common.bindings_saved'));
    }

    public function bindWorkflow(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|string|max:50',
            'workflow_id' => 'required|exists:approval_workflows,id',
            'redirect' => 'nullable|string|in:matrix',
        ]);

        $workflow = ApprovalWorkflow::query()->findOrFail($validated['workflow_id']);
        if ($workflow->document_type !== $validated['document_type']) {
            return redirect()
                ->back()
                ->with('error', __('common.workflow_binding_type_mismatch'));
        }

        DepartmentWorkflowBinding::updateOrCreate(
            [
                'department_id' => $department->id,
                'document_type' => $validated['document_type'],
            ],
            ['workflow_id' => (int) $validated['workflow_id']]
        );

        if (($validated['redirect'] ?? null) === 'matrix') {
            return redirect()
                ->route('settings.department-workflow-bindings.index')
                ->with('success', __('common.saved'));
        }

        return redirect()
            ->route('settings.departments.edit', $department)
            ->with('success', __('common.saved'));
    }

    private function normalizeCode(mixed $raw): string
    {
        return strtoupper(trim((string) $raw));
    }
}
