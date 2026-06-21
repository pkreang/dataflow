<?php

namespace App\Http\Controllers\Web\Cmms;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\PmWorkOrder;
use App\Models\PmWorkOrderItem;
use App\Services\Cmms\PmWorkOrderGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PmWorkOrderController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $query = PmWorkOrder::with(['equipment', 'plan', 'assignee', 'completedBy']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('equipment', fn ($e) => $e->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('plan', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($equipmentId = $request->input('equipment_id')) {
            $query->where('equipment_id', $equipmentId);
        }

        $perPage = $this->resolvePerPage($request, 'pm_work_orders_per_page');
        $workOrders = $query->orderByRaw("FIELD(status, 'in_progress', 'overdue', 'due', 'done', 'skipped', 'cancelled')")
            ->orderBy('due_date')
            ->paginate($perPage)
            ->withQueryString();

        $equipmentList = Equipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $statuses = PmWorkOrder::STATUSES;

        return view('cmms.pm.work-orders.index', compact('workOrders', 'equipmentList', 'statuses', 'perPage'));
    }

    public function show(PmWorkOrder $workOrder): View
    {
        $workOrder->load(['equipment', 'plan', 'items.sparePart', 'assignee', 'completedBy']);

        return view('cmms.pm.work-orders.show', compact('workOrder'));
    }

    public function start(PmWorkOrder $workOrder): RedirectResponse
    {
        abort_if(! in_array($workOrder->status, ['due', 'overdue'], true), 422, __('common.pm_wo_cannot_start'));

        $workOrder->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to_user_id' => $workOrder->assigned_to_user_id ?? Auth::id(),
        ]);

        return redirect()->route('cmms.pm.work-orders.show', $workOrder)->with('success', __('common.pm_wo_started'));
    }

    public function complete(Request $request, PmWorkOrder $workOrder): RedirectResponse
    {
        abort_if($workOrder->status !== 'in_progress', 422, __('common.pm_wo_must_be_in_progress'));

        $workOrder->load('items', 'plan', 'equipment');

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:pm_work_order_items,id'],
            'items.*.status' => ['required', Rule::in(PmWorkOrderItem::STATUSES)],
            'items.*.actual_value' => ['nullable', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.photo' => ['nullable', 'file', 'image', 'max:5120'],
            'findings' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'current_runtime_hours' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        DB::transaction(function () use ($workOrder, $validated, $request) {
            $now = now();
            $user = Auth::user();

            foreach ($validated['items'] as $payload) {
                $item = $workOrder->items->firstWhere('id', $payload['id']);
                if (! $item) {
                    continue;
                }

                $photoPath = $item->photo_path;
                if ($request->hasFile("items.{$payload['id']}.photo")) {
                    $photoPath = $request->file("items.{$payload['id']}.photo")
                        ->store("pm/wo/{$workOrder->id}", 'public');
                }

                $item->update([
                    'status' => $payload['status'],
                    'actual_value' => $payload['actual_value'] ?? null,
                    'note' => $payload['note'] ?? null,
                    'photo_path' => $photoPath,
                    'completed_at' => $payload['status'] !== 'pending' ? $now : null,
                    'completed_by_user_id' => $payload['status'] !== 'pending' ? $user->id : null,
                ]);
            }

            // Update equipment runtime if user reported it
            $currentRuntime = $validated['current_runtime_hours'] ?? null;
            if ($currentRuntime !== null) {
                $workOrder->equipment->update(['runtime_hours' => $currentRuntime]);
            } else {
                $currentRuntime = $workOrder->equipment->runtime_hours ? (float) $workOrder->equipment->runtime_hours : null;
            }

            $workOrder->update([
                'status' => 'done',
                'completed_at' => $now,
                'completed_by_user_id' => $user->id,
                'findings' => $validated['findings'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Advance plan due markers if this WO was from a plan
            if ($workOrder->plan) {
                app(PmWorkOrderGenerator::class)
                    ->advancePlanAfterCompletion($workOrder->plan, $now, $currentRuntime);
            }
        });

        return redirect()->route('cmms.pm.work-orders.show', $workOrder)->with('success', __('common.pm_wo_completed'));
    }

    public function cancel(PmWorkOrder $workOrder): RedirectResponse
    {
        abort_if(! in_array($workOrder->status, ['due', 'in_progress', 'overdue'], true), 422, __('common.pm_wo_cannot_cancel'));

        $workOrder->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'completed_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('cmms.pm.work-orders.index')->with('success', __('common.pm_wo_cancelled'));
    }
}
