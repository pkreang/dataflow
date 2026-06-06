<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentFormWorkflowPolicyController extends Controller
{
    public function edit(DocumentForm $documentForm): View
    {
        $policy = DocumentFormWorkflowPolicy::query()
            ->with('ranges')
            ->firstOrCreate(
                ['form_id' => $documentForm->id, 'department_id' => null],
                ['use_amount_condition' => false]
            );

        $workflows = ApprovalWorkflow::query()
            ->where('document_type', $documentForm->document_type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();

        return view('settings.document-forms.policy', compact('documentForm', 'policy', 'workflows', 'departments'));
    }

    public function update(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => 'nullable|integer|exists:departments,id',
            'use_amount_condition' => 'nullable|boolean',
            'amount_field_key' => 'nullable|string|max:100',
            'workflow_id' => 'nullable|integer|exists:approval_workflows,id',
            'ranges' => 'nullable|array',
            'ranges.*.min_amount' => 'required_with:ranges|numeric|min:0',
            'ranges.*.max_amount' => 'nullable|numeric|min:0',
            'ranges.*.workflow_id' => 'required_with:ranges|integer|exists:approval_workflows,id',
        ]);

        $useAmountCondition = (bool) ($validated['use_amount_condition'] ?? false);
        if (! $useAmountCondition && empty($validated['workflow_id'])) {
            throw ValidationException::withMessages(['workflow_id' => 'Workflow is required for sequential mode.']);
        }

        if ($useAmountCondition) {
            $ranges = $validated['ranges'] ?? [];
            if (count($ranges) === 0) {
                throw ValidationException::withMessages(['ranges' => 'At least one amount range is required.']);
            }
            $this->validateRanges($ranges);
        }

        DB::transaction(function () use ($validated, $documentForm, $useAmountCondition) {
            $policy = DocumentFormWorkflowPolicy::updateOrCreate(
                [
                    'form_id' => $documentForm->id,
                    'department_id' => $validated['department_id'] ?? null,
                ],
                [
                    'use_amount_condition' => $useAmountCondition,
                    'amount_field_key' => $useAmountCondition ? ($validated['amount_field_key'] ?? null) : null,
                    'workflow_id' => $useAmountCondition ? null : (int) $validated['workflow_id'],
                ]
            );

            $policy->ranges()->delete();
            if ($useAmountCondition) {
                foreach (($validated['ranges'] ?? []) as $index => $range) {
                    $policy->ranges()->create([
                        'min_amount' => $range['min_amount'],
                        'max_amount' => $range['max_amount'] ?? null,
                        'workflow_id' => (int) $range['workflow_id'],
                        'sort_order' => $index + 1,
                    ]);
                }
            }
        });

        return redirect()->route('settings.document-forms.policy.edit', $documentForm)->with('success', __('common.saved'));
    }

    private function validateRanges(array $ranges): void
    {
        usort($ranges, fn ($a, $b) => (float) $a['min_amount'] <=> (float) $b['min_amount']);
        $previousMax = null;

        foreach ($ranges as $index => $range) {
            $min = (float) $range['min_amount'];
            $max = isset($range['max_amount']) && $range['max_amount'] !== '' ? (float) $range['max_amount'] : null;

            if ($max !== null && $min > $max) {
                throw ValidationException::withMessages([
                    "ranges.{$index}.max_amount" => 'max_amount must be greater than or equal to min_amount.',
                ]);
            }

            if ($previousMax !== null && $min < $previousMax) {
                throw ValidationException::withMessages([
                    "ranges.{$index}.min_amount" => 'Amount ranges overlap.',
                ]);
            }
            $previousMax = $max;
        }
    }
}
