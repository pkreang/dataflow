<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\Equipment;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MaintenanceController extends Controller
{
    use HasPerPage;

    public function __construct(
        protected ApprovalFlowService $approvalFlow,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $status = $request->query('status');
        if ($status !== null && $status !== '' && ! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = null;
        }

        $perPage = $this->resolvePerPage($request, 'maintenance_per_page');
        $myInstances = ApprovalInstance::query()
            ->where('document_type', 'pm_am_plan')
            ->where('requester_user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['department', 'orgUnit'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('maintenance.index', compact('myInstances', 'status', 'perPage'));
    }

    public function createPlan(): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;
        $userOrgUnitId = session('user.org_unit_id') ?? User::find($userId)?->org_unit_id;
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $form = DocumentForm::query()
            ->with('fields')
            ->where('document_type', 'pm_am_plan')
            ->where('is_active', true)
            ->visibleToUser($userOrgUnitId, $userDeptId)
            ->orderBy('id')
            ->first();
        $equipmentList = Equipment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        $userModel = $userId > 0 ? User::with(['company', 'branch'])->find($userId) : null;
        $company = $userModel?->company;
        $branch = null;
        if ($userModel && $userModel->branch && $userModel->branch->is_active
            && (int) $userModel->branch->company_id === (int) $userModel->company_id) {
            $branch = $userModel->branch;
        }

        return view('maintenance.create-plan', compact('departments', 'form', 'equipmentList', 'company', 'branch', 'userDeptId', 'userOrgUnitId'));
    }

    public function autoAssign(): View
    {
        return view('maintenance.auto-assign');
    }

    public function submitPlan(Request $request, ApprovalFlowService $approvalFlowService): RedirectResponse
    {
        $validated = $request->validate([
            'reference_no' => 'nullable|string|max:100',
            'form_key' => 'nullable|string|max:100',
            'form_payload' => 'nullable|array',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $payload = $validated['form_payload'] ?? [];
        if (isset($payload['title']) && trim((string) $payload['title']) === '') {
            return back()
                ->withErrors(['form_payload.title' => __('common.validation_title_required')])
                ->withInput();
        }

        try {
            $instance = $approvalFlowService->start(
                'pm_am_plan',
                null,
                (int) (session('user.id') ?? 1),
                $validated['reference_no'] ?? null,
                $payload,
                $validated['form_key'] ?? null,
                isset($validated['amount']) ? (float) $validated['amount'] : null,
            );
        } catch (RuntimeException $e) {
            return back()
                ->withErrors(['workflow' => $this->workflowErrorMessage($e)])
                ->withInput();
        }

        return redirect()
            ->route('maintenance.show', $instance)
            ->with('success', __('common.saved'));
    }

    public function show(ApprovalInstance $instance): View
    {
        abort_unless($instance->document_type === 'pm_am_plan', 404);
        $this->authorizeViewInstance($instance);

        $instance->load(['steps.actor', 'workflow', 'requester.company', 'requester.branch', 'department', 'orgUnit']);
        $userId = (int) (session('user.id') ?? 0);

        $formForLabels = DocumentForm::query()
            ->with('fields')
            ->where('document_type', 'pm_am_plan')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        $formFields = $formForLabels?->fields ?? collect();

        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;
        $userOrgUnitId = session('user.org_unit_id') ?? User::find($userId)?->org_unit_id;
        $editorRole = $this->resolveEditorRole($instance, $userId);

        $canAct = false;
        if ($instance->status === 'pending' && in_array('approval.approve', session('user_permissions', []), true)) {
            $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);
            if ($currentStep && $currentStep->action === 'pending') {
                $canAct = $this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId);
            }
        }

        $requester = $instance->requester;
        $company = $requester?->company;
        $branch = null;
        if ($requester && $requester->branch && $requester->branch->is_active
            && (int) $requester->branch->company_id === (int) $requester->company_id) {
            $branch = $requester->branch;
        }

        return view('maintenance.show', compact('instance', 'canAct', 'formFields', 'formForLabels', 'userDeptId', 'userOrgUnitId', 'editorRole', 'company', 'branch'));
    }

    private function resolveEditorRole(ApprovalInstance $instance, int $userId): string
    {
        if ($instance->status !== 'pending') {
            return 'view_only';
        }
        $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);
        if ($currentStep && $currentStep->action === 'pending' && $this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId)) {
            return 'step_'.$instance->current_step_no;
        }

        return 'view_only';
    }

    private function authorizeViewInstance(ApprovalInstance $instance): void
    {
        if (session('user.is_super_admin', false)) {
            return;
        }
        $uid = (int) (session('user.id') ?? 0);
        if ($instance->requester_user_id === $uid) {
            return;
        }
        if (in_array('approval.approve', session('user_permissions', []), true)) {
            return;
        }
        abort(403);
    }

    private function workflowErrorMessage(RuntimeException $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, 'Amount is required for amount-based') => __('common.workflow_error_amount_required'),
            str_contains($msg, 'No matching amount range') => __('common.workflow_error_no_amount_range'),
            str_contains($msg, 'Department is required for workflow binding') => __('common.workflow_error_department_required'),
            str_contains($msg, 'No workflow binding found') => __('common.workflow_error_no_binding'),
            str_contains($msg, 'Workflow is not configured') => __('common.workflow_error_not_configured'),
            default => __('common.workflow_error_generic'),
        };
    }
}
