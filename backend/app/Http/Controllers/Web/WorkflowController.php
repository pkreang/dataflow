<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\OrgUnitWorkflowBinding;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class WorkflowController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'workflows_per_page');
        $workflows = ApprovalWorkflow::query()
            ->withCount('stages')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('settings.workflow.index', compact('workflows', 'perPage'));
    }

    public function create(): View
    {
        $roles = Role::query()->orderBy('name')->pluck('name');
        $users = User::query()
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'label' => trim($u->first_name.' '.$u->last_name).' ('.$u->email.')',
            ]);
        $usersByPosition = $this->usersByPosition();
        $positions = Position::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Position $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'label' => $p->name.' ('.$p->code.')',
                'users' => $usersByPosition[$p->id] ?? [],
            ]);

        $allowRequesterOverride = Setting::getBool('approval.allow_requester_override', false);

        return view('settings.workflow.create', compact('roles', 'users', 'positions', 'allowRequesterOverride'));
    }

    public function edit(ApprovalWorkflow $workflow): View
    {
        $workflow->load(['stages' => fn ($q) => $q->orderBy('step_no')]);
        $roles = Role::query()->orderBy('name')->pluck('name');
        $users = User::query()
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'label' => trim($u->first_name.' '.$u->last_name).' ('.$u->email.')',
            ]);
        $usedPositionIds = $workflow->stages
            ->where('approver_type', 'position')
            ->pluck('approver_ref')
            ->map(fn ($r) => (int) $r)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $usersByPosition = $this->usersByPosition();
        $positions = Position::query()
            ->where(function ($q) use ($usedPositionIds) {
                $q->where('is_active', true);
                if ($usedPositionIds !== []) {
                    $q->orWhereIn('id', $usedPositionIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Position $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'label' => $p->name.' ('.$p->code.')',
                'users' => $usersByPosition[$p->id] ?? [],
            ]);

        $allowRequesterOverride = Setting::getBool('approval.allow_requester_override', false);

        return view('settings.workflow.edit', compact('workflow', 'roles', 'users', 'positions', 'allowRequesterOverride'));
    }

    /**
     * @return array<int, list<string>>
     */
    private function usersByPosition(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('position_id')
            ->orderBy('first_name')
            ->get(['position_id', 'first_name', 'last_name'])
            ->groupBy('position_id')
            ->map(fn ($group) => $group->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->values()->all())
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'allow_requester_as_approver' => 'nullable|in:0,1',
            'stages' => 'required|array|min:1',
            'stages.*.step_no' => 'required|integer|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.approver_type' => 'required|in:role,user,position,direct_manager,org_head,org_parent_head,org_n_up',
            'stages.*.approver_ref' => 'nullable|string|max:255',
            'stages.*.approver_rules' => 'nullable|string',
            'stages.*.min_approvals' => 'required|integer|min:1',
            'stages.*.require_signature' => 'nullable|boolean',
            'stages.*.allow_requester_override' => 'nullable|boolean',
            'stages.*.escalation_after_days' => 'nullable|integer|min:1|max:365',
        ]);

        $this->validateUniqueSteps($validated['stages']);
        $this->validateApproverRefs($validated['stages']);

        DB::transaction(function () use ($validated) {
            $workflow = ApprovalWorkflow::create([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'allow_requester_as_approver' => (bool) (int) ($validated['allow_requester_as_approver'] ?? 1),
            ]);

            foreach ($validated['stages'] as $stage) {
                $rulesJson = $stage['approver_rules'] ?? null;
                ApprovalWorkflowStage::create([
                    'workflow_id' => $workflow->id,
                    'step_no' => (int) $stage['step_no'],
                    'name' => $stage['name'],
                    'approver_type' => $stage['approver_type'],
                    'approver_ref' => $stage['approver_ref'] ?? '',
                    'approver_rules' => $this->sanitizeApproverRules(($rulesJson && $rulesJson !== '[]') ? json_decode($rulesJson, true) : null),
                    'min_approvals' => (int) $stage['min_approvals'],
                    'require_signature' => (bool) ($stage['require_signature'] ?? false),
                    'allow_requester_override' => (bool) ($stage['allow_requester_override'] ?? false),
                    'escalation_after_days' => isset($stage['escalation_after_days']) && $stage['escalation_after_days'] !== '' ? (int) $stage['escalation_after_days'] : null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('settings.workflow.index')->with('success', __('common.saved'));
    }

    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $workflow->update(['is_active' => ! $workflow->is_active]);

            return redirect()->route('settings.workflow.index')->with('success', __('common.saved'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'allow_requester_as_approver' => 'nullable|in:0,1',
            'stages' => 'required|array|min:1',
            'stages.*.step_no' => 'required|integer|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.approver_type' => 'required|in:role,user,position,direct_manager,org_head,org_parent_head,org_n_up',
            'stages.*.approver_ref' => 'nullable|string|max:255',
            'stages.*.approver_rules' => 'nullable|string',
            'stages.*.min_approvals' => 'required|integer|min:1',
            'stages.*.require_signature' => 'nullable|boolean',
            'stages.*.allow_requester_override' => 'nullable|boolean',
            'stages.*.escalation_after_days' => 'nullable|integer|min:1|max:365',
        ]);

        $this->validateUniqueSteps($validated['stages']);
        $this->validateApproverRefs($validated['stages']);

        DB::transaction(function () use ($workflow, $validated) {
            $workflow->update([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'allow_requester_as_approver' => (bool) (int) ($validated['allow_requester_as_approver'] ?? 1),
            ]);

            $workflow->stages()->delete();
            foreach ($validated['stages'] as $stage) {
                $rulesJson = $stage['approver_rules'] ?? null;
                ApprovalWorkflowStage::create([
                    'workflow_id' => $workflow->id,
                    'step_no' => (int) $stage['step_no'],
                    'name' => $stage['name'],
                    'approver_type' => $stage['approver_type'],
                    'approver_ref' => $stage['approver_ref'] ?? '',
                    'approver_rules' => $this->sanitizeApproverRules(($rulesJson && $rulesJson !== '[]') ? json_decode($rulesJson, true) : null),
                    'min_approvals' => (int) $stage['min_approvals'],
                    'require_signature' => (bool) ($stage['require_signature'] ?? false),
                    'allow_requester_override' => (bool) ($stage['allow_requester_override'] ?? false),
                    'escalation_after_days' => isset($stage['escalation_after_days']) && $stage['escalation_after_days'] !== '' ? (int) $stage['escalation_after_days'] : null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('settings.workflow.edit', $workflow)->with('success', __('common.updated'));
    }

    public function addStage(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'step_no' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'approver_type' => 'required|in:role,user,position,direct_manager,org_head,org_parent_head,org_n_up',
            'approver_ref' => 'nullable|string|max:255',
            'min_approvals' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $this->validateApproverRefs([$validated]);

        ApprovalWorkflowStage::updateOrCreate(
            [
                'workflow_id' => $workflow->id,
                'step_no' => (int) $validated['step_no'],
            ],
            [
                'name' => $validated['name'],
                'approver_type' => $validated['approver_type'],
                'approver_ref' => $validated['approver_ref'] ?? '',
                'min_approvals' => (int) $validated['min_approvals'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]
        );

        return redirect()->route('settings.workflow.index')->with('success', __('common.saved'));
    }

    public function destroy(ApprovalWorkflow $workflow): RedirectResponse
    {
        if (ApprovalInstance::where('workflow_id', $workflow->id)->exists()
            || OrgUnitWorkflowBinding::where('workflow_id', $workflow->id)->exists()) {
            return redirect()->route('settings.workflow.index')
                ->with('error', __('common.cannot_delete_workflow'));
        }

        $workflow->stages()->delete();
        $workflow->delete();

        return redirect()->route('settings.workflow.index')->with('success', __('common.deleted'));
    }

    private function validateUniqueSteps(array $stages): void
    {
        $steps = array_map(fn ($s) => (int) ($s['step_no'] ?? 0), $stages);
        if (count($steps) !== count(array_unique($steps))) {
            abort(422, __('common.workflow_duplicate_step'));
        }
    }

    private function sanitizeApproverRules(?array $rules): ?array
    {
        if (! $rules) {
            return null;
        }

        return array_map(function (array $rule) {
            $rule['min_count'] = max(1, (int) ($rule['min_count'] ?? 1));

            return $rule;
        }, $rules);
    }

    private function validateApproverRefs(array $stages): void
    {
        $roleNames = Role::query()->pluck('name')->all();
        $userIds = User::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $positionIds = Position::query()->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($stages as $index => $stage) {
            $type = $stage['approver_type'] ?? null;
            $ref = (string) ($stage['approver_ref'] ?? '');

            if ($type === 'direct_manager') {
                continue; // resolved at submit time from requester's manager_id
            }

            if (in_array($type, ['org_head', 'org_parent_head', 'org_n_up'], true)) {
                continue; // resolved at submit time from requester's org_unit_id
            }

            if ($type === 'role' && ! in_array($ref, $roleNames, true)) {
                throw ValidationException::withMessages([
                    "stages.{$index}.approver_ref" => __('common.workflow_role_not_found'),
                ]);
            }

            if ($type === 'user' && ! in_array($ref, $userIds, true)) {
                throw ValidationException::withMessages([
                    "stages.{$index}.approver_ref" => __('common.workflow_user_not_found'),
                ]);
            }

            if ($type === 'position' && ! in_array($ref, $positionIds, true)) {
                throw ValidationException::withMessages([
                    "stages.{$index}.approver_ref" => __('common.workflow_position_not_found'),
                ]);
            }
        }
    }
}
