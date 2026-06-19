<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
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

        $perPage = $this->resolvePerPage($request, 'purchase_orders_per_page');
        $myInstances = ApprovalInstance::query()
            ->where('document_type', 'purchase_order')
            ->where('requester_user_id', $userId)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('purchase-orders.index', compact('myInstances', 'status', 'perPage'));
    }

    public function create(Request $request): View
    {
        $prInstance = null;
        $prLineItems = collect();

        if ($fromPrId = $request->query('from_pr')) {
            $prInstance = ApprovalInstance::find($fromPrId);
            if ($prInstance) {
                abort_unless($prInstance->document_type === 'purchase_request', 422);
                if ($prInstance->status !== 'approved') {
                    return redirect()->route('purchase-requests.show', $prInstance)
                        ->withErrors(['workflow' => __('common.pr_not_approved')]);
                }
                $exists = ApprovalInstance::where('document_type', 'purchase_order')
                    ->whereRaw("json_extract(payload, '$.purchase_request_id') = ?", [$prInstance->id])
                    ->exists();
                if ($exists) {
                    return redirect()->route('purchase-requests.show', $prInstance)
                        ->withErrors(['workflow' => __('common.po_already_exists')]);
                }
                $prLineItems = PurchaseRequestItem::where('approval_instance_id', $prInstance->id)->get();
            }
        }

        $form = DocumentForm::query()
            ->with('fields')
            ->where('document_type', 'purchase_order')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $userId = (int) (session('user.id') ?? 0);
        $userModel = $userId > 0 ? User::with(['company', 'branch'])->find($userId) : null;
        $company = $userModel?->company;
        $branch = null;
        if ($userModel && $userModel->branch && $userModel->branch->is_active
            && (int) $userModel->branch->company_id === (int) $userModel->company_id) {
            $branch = $userModel->branch;
        }

        return view('purchase-orders.create', compact('form', 'prInstance', 'prLineItems', 'company', 'branch'));
    }

    public function store(Request $request, ApprovalFlowService $approvalFlowService): RedirectResponse
    {
        $validated = $request->validate([
            'form_key' => 'nullable|string|max:100',
            'form_payload' => 'nullable|array',
            'purchase_request_id' => 'nullable|integer|exists:approval_instances,id',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        // Guard: check PR is approved and no duplicate PO
        if ($prId = $validated['purchase_request_id'] ?? null) {
            $pr = ApprovalInstance::find($prId);
            if (! $pr || $pr->status !== 'approved') {
                return back()->withErrors(['workflow' => __('common.pr_not_approved')])->withInput();
            }
            $exists = ApprovalInstance::where('document_type', 'purchase_order')
                ->whereRaw("json_extract(payload, '$.purchase_request_id') = ?", [$prId])
                ->exists();
            if ($exists) {
                return back()->withErrors(['workflow' => __('common.po_already_exists')])->withInput();
            }
        }

        $payload = $validated['form_payload'] ?? [];
        $totalAmount = array_sum(array_column($validated['items'], 'total_price'));

        if ($prId && isset($pr)) {
            $payload['purchase_request_id'] = $pr->id;
            $payload['parent_reference'] = $pr->reference_no ?? 'PR#'.$pr->id;
        }

        try {
            $instance = $approvalFlowService->start(
                'purchase_order',
                (int) (session('user.id') ?? 0),
                null,
                $payload,
                $validated['form_key'] ?? null,
                $totalAmount > 0 ? (float) $totalAmount : null
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['workflow' => $this->workflowErrorMessage($e)])->withInput();
        }

        foreach ($validated['items'] as $item) {
            PurchaseOrderItem::create([
                'approval_instance_id' => $instance->id,
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return redirect()->route('purchase-orders.show', $instance)->with('success', __('common.saved'));
    }

    public function show(ApprovalInstance $instance): View
    {
        abort_unless($instance->document_type === 'purchase_order', 404);
        $this->authorizeViewInstance($instance);

        $instance->load(['steps.actor', 'workflow', 'requester.company', 'requester.branch']);
        $userId = (int) (session('user.id') ?? 0);

        $lineItems = PurchaseOrderItem::where('approval_instance_id', $instance->id)->get();

        $formForLabels = DocumentForm::query()->with('fields')
            ->where('document_type', 'purchase_order')->where('is_active', true)->orderBy('id')->first();
        $formFields = $formForLabels?->fields ?? collect();

        $editorRole = $this->resolveEditorRole($instance, $userId);

        $canAct = false;
        if ($instance->status === 'pending' && in_array('approval.approve', session('user_permissions', []), true)) {
            $currentStep = $instance->steps->firstWhere('step_no', $instance->current_step_no);
            if ($currentStep && $currentStep->action === 'pending') {
                $canAct = $this->approvalFlow->canUserActOnStep($instance, $currentStep, $userId);
            }
        }

        // Link back to source PR
        $sourcePr = null;
        if ($prId = $instance->payload['purchase_request_id'] ?? null) {
            $sourcePr = ApprovalInstance::find($prId);
        }

        $requester = $instance->requester;
        $company = $requester?->company;
        $branch = null;
        if ($requester && $requester->branch && $requester->branch->is_active
            && (int) $requester->branch->company_id === (int) $requester->company_id) {
            $branch = $requester->branch;
        }

        return view('purchase-orders.show', compact(
            'instance', 'lineItems', 'canAct', 'sourcePr',
            'formFields', 'formForLabels', 'editorRole', 'company', 'branch'
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
            str_contains($msg, 'No workflow binding found') => __('common.workflow_error_no_binding'),
            str_contains($msg, 'Workflow is not configured') => __('common.workflow_error_not_configured'),
            default => __('common.workflow_error_generic'),
        };
    }
}
