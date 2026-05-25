<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\KpiCycle;
use App\Models\KpiCycleAssignment;
use App\Models\User;
use App\Services\Kpi\KpiCycleOpener;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin CRUD + lifecycle endpoints for KPI cycles.
 *
 *  - draft  → admin builds the assignment list
 *  - open() → KpiCycleOpener spawns one draft submission per assignment
 *  - close()→ locks the cycle for reporting (Phase 3)
 *
 * Permission gating is at the route level (`permission:kpi.manage`).
 */
class KpiCycleController extends Controller
{
    public function __construct(
        protected KpiCycleOpener $opener,
    ) {}

    public function index(): View
    {
        $cycles = KpiCycle::query()
            ->with(['form', 'assignments'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('settings.kpi-cycles.index', compact('cycles'));
    }

    public function create(): View
    {
        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'document_type']);

        return view('settings.kpi-cycles.create', compact('forms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'form_id' => ['required', 'integer', 'exists:document_forms,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $userId = (int) (session('user.id') ?? 0);

        $cycle = KpiCycle::query()->create([
            'name' => $validated['name'],
            'form_id' => $validated['form_id'],
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'status' => KpiCycle::STATUS_DRAFT,
            'created_by_user_id' => $userId ?: null,
        ]);

        return redirect()->route('settings.kpi-cycles.edit', $cycle)
            ->with('success', __('common.kpi_cycle_created'));
    }

    public function edit(KpiCycle $kpiCycle): View
    {
        $kpiCycle->load(['form', 'assignments.target', 'assignments.evaluator', 'assignments.submission']);
        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('settings.kpi-cycles.edit', [
            'cycle' => $kpiCycle,
            'forms' => $forms,
            'users' => $users,
        ]);
    }

    public function update(Request $request, KpiCycle $kpiCycle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'form_id' => ['required', 'integer', 'exists:document_forms,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.target_user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.evaluator_user_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.role' => ['nullable', 'string', 'in:self,supervisor,peer'],
        ]);

        DB::transaction(function () use ($kpiCycle, $validated) {
            $kpiCycle->update([
                'name' => $validated['name'],
                'form_id' => $validated['form_id'],
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
            ]);

            // Assignments are editable only while the cycle is in draft — once
            // opened, submissions exist and rewriting the assignment list
            // would orphan them.
            if ($kpiCycle->status === KpiCycle::STATUS_DRAFT && array_key_exists('assignments', $validated)) {
                $kpiCycle->assignments()->delete();
                foreach ($validated['assignments'] ?? [] as $row) {
                    KpiCycleAssignment::query()->create([
                        'cycle_id' => $kpiCycle->id,
                        'target_user_id' => (int) $row['target_user_id'],
                        'evaluator_user_id' => (int) $row['evaluator_user_id'],
                        'role' => $row['role'] ?? KpiCycleAssignment::ROLE_SUPERVISOR,
                    ]);
                }
            }
        });

        return redirect()->route('settings.kpi-cycles.edit', $kpiCycle)
            ->with('success', __('common.saved'));
    }

    public function destroy(KpiCycle $kpiCycle): RedirectResponse
    {
        if ($kpiCycle->status !== KpiCycle::STATUS_DRAFT) {
            return redirect()->route('settings.kpi-cycles.index')
                ->with('error', __('common.kpi_cycle_only_draft_can_delete'));
        }

        $kpiCycle->assignments()->delete();
        $kpiCycle->delete();

        return redirect()->route('settings.kpi-cycles.index')
            ->with('success', __('common.deleted'));
    }

    public function open(KpiCycle $kpiCycle): RedirectResponse
    {
        try {
            $this->opener->open($kpiCycle);
        } catch (RuntimeException $e) {
            return back()->withErrors(['kpi_cycle' => $e->getMessage()]);
        }

        return redirect()->route('settings.kpi-cycles.edit', $kpiCycle)
            ->with('success', __('common.kpi_cycle_opened'));
    }

    public function close(KpiCycle $kpiCycle): RedirectResponse
    {
        try {
            $this->opener->close($kpiCycle);
        } catch (RuntimeException $e) {
            return back()->withErrors(['kpi_cycle' => $e->getMessage()]);
        }

        return redirect()->route('settings.kpi-cycles.edit', $kpiCycle)
            ->with('success', __('common.kpi_cycle_closed'));
    }
}
