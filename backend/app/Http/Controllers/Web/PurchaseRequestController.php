<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseRequestController extends Controller
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

        $perPage = $this->resolvePerPage($request, 'purchase_requests_per_page');
        $myInstances = ApprovalInstance::query()
            ->where('document_type', 'purchase_request')
            ->where('requester_user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['department'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase-requests.index', compact('myInstances', 'status', 'perPage'));
    }

    public function create(): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $userDeptId = session('user.department_id') ?? User::find($userId)?->department_id;
        $userOrgUnitId = session('user.org_unit_id') ?? User::find($userId)?->org_unit_id;
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $form = DocumentForm::query()
            ->with('fields')
            ->where('document_type', 'purchase_request')
            ->where('is_active', true)
            ->visibleToUser($userOrgUnitId, $userDeptId)
            ->orderBy('id')
            ->first();

        $userModel = $userId > 0 ? User::with(['company', 'branch'])->find($userId) : null;
        $company = $userModel?->company;
        $branch = null;
        if ($userModel && $userModel->branch && $userModel->branch->is_active
            && (int) $userModel->branch->company_id === (int) $userModel->company_id) {
            $branch = $userModel->branch;
        }

        return view('purchase-requests.create', compact('departments', 'form', 'company', 'branch', 'userDeptId', 'userOrgUnitId'));
    }

    public function store(Request $request, ApprovalFlowService $approvalFlowService): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => 'nullable|integer|exists:departments,id',
            'form_key' => 'nullable|string|max:100',
            'form_payload' => 'nullable|array',
            'amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        $payload = $validated['form_payload'] ?? [];
        $totalAmount = array_sum(array_column($validated['items'], 'total_price'));

        try {
            $instance = $approvalFlowService->start(
                'purchase_request',
                $validated['department_id'] ?? null,
                (int) (session('user.id') ?? 0),
                null,
                $payload,
                $validated['form_key'] ?? null,
                $totalAmount > 0 ? (float) $totalAmount : null,
                orgUnitId: \App\Models\OrgUnit::idForDepartment($validated['department_id'] ?? null)
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['workflow' => $this->workflowErrorMessage($e)])->withInput();
        }

        foreach ($validated['items'] as $item) {
            PurchaseRequestItem::create([
                'approval_instance_id' => $instance->id,
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return redirect()->route('purchase-requests.show', $instance)->with('success', __('common.saved'));
    }

    public function show(ApprovalInstance $instance): View
    {
        abort_unless($instance->document_type === 'purchase_request', 404);
        $this->authorizeViewInstance($instance);

        $instance->load(['steps.actor', 'workflow', 'requester.company', 'requester.branch', 'department']);
        $userId = (int) (session('user.id') ?? 0);

        $lineItems = PurchaseRequestItem::where('approval_instance_id', $instance->id)->get();

        $formForLabels = DocumentForm::query()->with('fields')
            ->where('document_type', 'purchase_request')->where('is_active', true)->orderBy('id')->first();
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

        // Show "Create PO" button when PR is approved and user has permission
        $canCreatePo = $instance->status === 'approved'
            && (session('user.is_super_admin', false) || in_array('purchase_order.create', session('user_permissions', []), true))
            && ! ApprovalInstance::where('document_type', 'purchase_order')
                ->whereRaw("json_extract(payload, '$.purchase_request_id') = ?", [$instance->id])
                ->exists();

        $requester = $instance->requester;
        $company = $requester?->company;
        $branch = null;
        if ($requester && $requester->branch && $requester->branch->is_active
            && (int) $requester->branch->company_id === (int) $requester->company_id) {
            $branch = $requester->branch;
        }

        return view('purchase-requests.show', compact(
            'instance', 'lineItems', 'canAct', 'canCreatePo',
            'formFields', 'formForLabels', 'userDeptId', 'userOrgUnitId', 'editorRole', 'company', 'branch'
        ));
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
            str_contains($msg, 'Department is required') => __('common.workflow_error_department_required'),
            str_contains($msg, 'No workflow binding found') => __('common.workflow_error_no_binding'),
            str_contains($msg, 'Workflow is not configured') => __('common.workflow_error_not_configured'),
            default => __('common.workflow_error_generic'),
        };
    }
}
