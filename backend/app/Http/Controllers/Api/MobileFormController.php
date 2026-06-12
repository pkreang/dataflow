<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\LookupList;
use App\Models\SubmissionActivityLog;
use App\Services\ApprovalFlowService;
use App\Services\LeaveValidationService;
use App\Support\FormulaFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileFormController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->where('document_type', '!=', 'evaluation')
            ->visibleToUser($user->department_id)
            ->orderBy('name')
            ->get(['id', 'form_key', 'name', 'document_type', 'description', 'layout_columns']);

        return response()->json([
            'success' => true,
            'data' => $forms->map(fn ($f) => [
                'id' => $f->id,
                'form_key' => $f->form_key,
                'name' => $f->name,
                'document_type' => $f->document_type,
                'description' => $f->description,
                'layout_columns' => $f->layout_columns,
            ]),
        ]);
    }

    public function show(string $formKey): JsonResponse
    {
        $form = DocumentForm::query()
            ->where('form_key', $formKey)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        // Pre-load lookup items for all lookup fields in one query
        $lookupKeys = $form->fields
            ->where('field_type', 'lookup')
            ->map(fn ($f) => data_get($f->options, 'source'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $lookupItemsMap = [];
        if ($lookupKeys) {
            LookupList::query()
                ->whereIn('list_key', $lookupKeys)
                ->with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->get()
                ->each(function ($list) use (&$lookupItemsMap) {
                    $lookupItemsMap[$list->list_key] = $list->items->map(fn ($item) => [
                        'value' => $item->value,
                        'label_en' => $item->label_en,
                        'label_th' => $item->label_th,
                    ])->values()->all();
                });
        }

        $fields = $form->fields->sortBy('sort_order')->map(function ($field) use ($lookupItemsMap) {
            $base = [
                'id' => $field->id,
                'field_key' => $field->field_key,
                'label' => $field->label,
                'label_en' => $field->label_en,
                'label_th' => $field->label_th,
                'field_type' => $field->field_type,
                'is_required' => (bool) $field->is_required,
                'is_readonly' => (bool) $field->is_readonly,
                'col_span' => $field->col_span,
                'sort_order' => $field->sort_order,
                'options' => $field->options,
                'placeholder' => $field->placeholder,
                'visibility_rules' => $field->visibility_rules ?? [],
                'required_rules' => $field->required_rules ?? [],
            ];

            // Attach resolved items for lookup fields
            if ($field->field_type === 'lookup') {
                $source = data_get($field->options, 'source');
                $base['lookup_items'] = $source ? ($lookupItemsMap[$source] ?? []) : [];
            }

            return $base;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $form->id,
                'form_key' => $form->form_key,
                'name' => $form->name,
                'document_type' => $form->document_type,
                'description' => $form->description,
                'layout_columns' => $form->layout_columns,
                'fields' => $fields->values(),
            ],
        ]);
    }

    public function submit(
        string $formKey,
        Request $request,
        ApprovalFlowService $approvalFlowService,
        LeaveValidationService $leaveValidator,
    ): JsonResponse {
        $form = DocumentForm::query()
            ->where('form_key', $formKey)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        abort_if($form->document_type === 'evaluation', 403, 'Evaluation forms cannot be submitted via mobile API.');

        $user = $request->user();
        $userId = $user->id;
        $userDeptId = $user->department_id;

        $payload = FormulaFields::recompute($form, (array) $request->input('fields', []));

        // Leave overlap guard
        if (isset($payload['date_from'], $payload['date_to'])) {
            try {
                $leaveValidator->checkOverlap(
                    userId: $userId,
                    formId: $form->id,
                    dateFrom: (string) $payload['date_from'],
                    dateTo: (string) $payload['date_to'],
                    excludeId: null,
                );
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        // Extract amount for amount-based routing
        $amount = null;
        $amountPolicy = \App\Models\DocumentFormWorkflowPolicy::query()
            ->where('form_id', $form->id)
            ->where('use_amount_condition', true)
            ->whereNotNull('amount_field_key')
            ->first();
        if ($amountPolicy?->amount_field_key) {
            $rawAmount = $payload[$amountPolicy->amount_field_key] ?? null;
            if (is_numeric($rawAmount)) {
                $amount = (float) $rawAmount;
            }
        }

        try {
            $instance = $approvalFlowService->start(
                documentType: $form->document_type,
                departmentId: $userDeptId,
                requesterUserId: $userId,
                referenceNo: null,
                payload: $payload,
                formKey: $form->form_key,
                amount: $amount,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $userId,
            'department_id' => $userDeptId,
            'payload' => $payload,
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        SubmissionActivityLog::record($submission->id, $userId, 'created');
        SubmissionActivityLog::record($submission->id, $userId, 'submitted');

        return response()->json([
            'success' => true,
            'data' => [
                'submission_id' => $submission->id,
                'reference_no' => $submission->reference_no,
                'status' => $submission->status,
            ],
        ], 201);
    }

    public function saveDraft(string $formKey, Request $request): JsonResponse
    {
        $form = DocumentForm::query()
            ->where('form_key', $formKey)
            ->where('is_active', true)
            ->firstOrFail();

        $user = $request->user();
        $payload = FormulaFields::recompute($form, (array) $request->input('fields', []));

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'payload' => $payload,
            'status' => 'draft',
        ]);

        SubmissionActivityLog::record($submission->id, $user->id, 'created');

        return response()->json([
            'success' => true,
            'data' => [
                'submission_id' => $submission->id,
                'status' => $submission->status,
            ],
        ], 201);
    }
}
