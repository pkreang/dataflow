<?php

namespace App\Http\Controllers\Web\Cmms;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\PmPlan;
use App\Models\PmTaskItem;
use App\Models\Position;
use App\Models\SparePart;
use App\Services\Cmms\PmWorkOrderGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PmPlanController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $query = PmPlan::with(['equipment', 'taskItems']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($equipmentId = $request->input('equipment_id')) {
            $query->where('equipment_id', $equipmentId);
        }

        if ($freqType = $request->input('frequency_type')) {
            $query->where('frequency_type', $freqType);
        }

        if ($request->input('is_active') !== null && $request->input('is_active') !== '') {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        $perPage = $this->resolvePerPage($request, 'pm_plans_per_page');
        $plans = $query->orderBy('equipment_id')->orderBy('name')->paginate($perPage)->withQueryString();
        $equipmentList = Equipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('cmms.pm.plans.index', compact('plans', 'equipmentList', 'perPage'));
    }

    public function create(): View
    {
        $plan = new PmPlan(['frequency_type' => 'date', 'is_active' => true]);
        $plan->setRelation('taskItems', collect());

        return view('cmms.pm.plans.create', $this->formData($plan));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $plan = DB::transaction(function () use ($validated) {
            $plan = PmPlan::create($this->planAttributes($validated));
            $this->syncTaskItems($plan, $validated['tasks'] ?? []);

            return $plan;
        });

        return redirect()->route('cmms.pm.plans.edit', $plan)->with('success', __('common.saved'));
    }

    public function edit(PmPlan $plan): View
    {
        $plan->load(['equipment', 'taskItems']);

        return view('cmms.pm.plans.edit', $this->formData($plan));
    }

    public function update(Request $request, PmPlan $plan): RedirectResponse
    {
        if ($request->has('toggle_active')) {
            $plan->update(['is_active' => ! $plan->is_active]);

            return redirect()->route('cmms.pm.plans.index')->with('success', __('common.saved'));
        }

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($plan, $validated) {
            $plan->update($this->planAttributes($validated));
            $plan->taskItems()->delete();
            $this->syncTaskItems($plan, $validated['tasks'] ?? []);
        });

        return redirect()->route('cmms.pm.plans.edit', $plan)->with('success', __('common.updated'));
    }

    public function destroy(PmPlan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('cmms.pm.plans.index')->with('success', __('common.deleted'));
    }

    public function generateWorkOrder(PmPlan $plan, PmWorkOrderGenerator $generator): RedirectResponse
    {
        if (! $plan->is_active) {
            return back()->with('error', __('common.pm_plan_inactive'));
        }
        if ($plan->taskItems()->count() === 0) {
            return back()->with('error', __('common.pm_plan_no_tasks'));
        }

        $wo = $generator->generate($plan);

        return redirect()
            ->route('cmms.pm.work-orders.show', $wo)
            ->with('success', __('common.pm_wo_generated', ['code' => $wo->code]));
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'equipment_id' => ['required', 'exists:equipment,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency_type' => ['required', Rule::in(PmPlan::FREQUENCY_TYPES)],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:frequency_type,date'],
            'interval_hours' => ['nullable', 'numeric', 'min:1', 'max:99999.99', 'required_if:frequency_type,runtime'],
            'assigned_to_position_id' => ['nullable', 'exists:positions,id'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'is_active' => ['nullable', 'boolean'],

            'tasks' => ['nullable', 'array'],
            'tasks.*.description' => ['required', 'string', 'max:500'],
            'tasks.*.task_type' => ['required', Rule::in(PmTaskItem::TASK_TYPES)],
            'tasks.*.expected_value' => ['nullable', 'string', 'max:255'],
            'tasks.*.unit' => ['nullable', 'string', 'max:50'],
            'tasks.*.requires_photo' => ['nullable', 'boolean'],
            'tasks.*.requires_signature' => ['nullable', 'boolean'],
            'tasks.*.spare_part_id' => ['nullable', 'exists:spare_parts,id'],
            'tasks.*.estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'tasks.*.loto_required' => ['nullable', 'boolean'],
            'tasks.*.is_critical' => ['nullable', 'boolean'],
        ]);
    }

    private function planAttributes(array $validated): array
    {
        $freq = $validated['frequency_type'];

        return [
            'equipment_id' => $validated['equipment_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'frequency_type' => $freq,
            'interval_days' => $freq === 'date' ? (int) $validated['interval_days'] : null,
            'interval_hours' => $freq === 'runtime' ? $validated['interval_hours'] : null,
            'assigned_to_position_id' => $validated['assigned_to_position_id'] ?? null,
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function syncTaskItems(PmPlan $plan, array $tasks): void
    {
        foreach (array_values($tasks) as $idx => $task) {
            $plan->taskItems()->create([
                'step_no' => $idx + 1,
                'sort_order' => $idx + 1,
                'description' => $task['description'],
                'task_type' => $task['task_type'],
                'expected_value' => $task['expected_value'] ?? null,
                'unit' => $task['unit'] ?? null,
                'requires_photo' => (bool) ($task['requires_photo'] ?? false),
                'requires_signature' => (bool) ($task['requires_signature'] ?? false),
                'spare_part_id' => $task['spare_part_id'] ?? null,
                'estimated_minutes' => $task['estimated_minutes'] ?? null,
                'loto_required' => (bool) ($task['loto_required'] ?? false),
                'is_critical' => (bool) ($task['is_critical'] ?? false),
            ]);
        }
    }

    private function formData(PmPlan $plan): array
    {
        $equipmentList = Equipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $positions = Position::orderBy('name')->get(['id', 'name']);
        $spareParts = SparePart::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return compact('plan', 'equipmentList', 'positions', 'spareParts');
    }
}
