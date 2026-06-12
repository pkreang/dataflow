<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Formula fields must be recomputed server-side on every mobile API write,
 * mirroring the web controllers — the client value is just a display mirror
 * and is spoofable. Critically, field-condition routing (e.g. total_days > 2)
 * depends on the computed value being present before the workflow starts.
 */
class MobileApiFormulaTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_mobile_submit_recomputes_formula_server_side(): void
    {
        [$form, $requester] = $this->makeLeaveFormWithWorkflow();

        Sanctum::actingAs($requester);

        $response = $this->postJson("/api/v1/mobile/forms/{$form->form_key}", [
            'fields' => [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-03',
                'total_days' => 999, // spoofed mirror — server must overwrite
            ],
        ]);

        $response->assertCreated();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame(3.0, (float) $submission->payload['total_days']);
    }

    public function test_mobile_submit_field_condition_routing_sees_computed_value(): void
    {
        [$form, $requester, $wfDefault] = $this->makeLeaveFormWithWorkflow();
        $approver = $this->makeRegularUser('mobile-formula-approver2@example.test');

        $wfLong = ApprovalWorkflow::create([
            'name' => 'Long leave (mobile formula test)',
            'document_type' => 'leave_request',
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wfLong->id, 'step_no' => 1, 'name' => 'Step 1',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);

        DocumentFormWorkflowPolicy::query()
            ->where('form_id', $form->id)
            ->update([
                'field_conditions' => [
                    ['field_key' => 'total_days', 'operator' => '>', 'value' => 2, 'workflow_id' => $wfLong->id, 'priority' => 1],
                ],
            ]);

        Sanctum::actingAs($requester);

        // 5 days — client does not even send total_days; server must compute it
        // before resolving the workflow, otherwise routing falls to the default.
        $this->postJson("/api/v1/mobile/forms/{$form->form_key}", [
            'fields' => ['date_from' => '2026-07-01', 'date_to' => '2026-07-05'],
        ])->assertCreated();

        $long = ApprovalInstance::query()->latest('id')->firstOrFail();
        $this->assertSame($wfLong->id, $long->workflow_id);

        // 1 day — stays on the default workflow
        $this->postJson("/api/v1/mobile/forms/{$form->form_key}", [
            'fields' => ['date_from' => '2026-08-01', 'date_to' => '2026-08-01'],
        ])->assertCreated();

        $short = ApprovalInstance::query()->latest('id')->firstOrFail();
        $this->assertSame($wfDefault->id, $short->workflow_id);
    }

    public function test_mobile_draft_save_recomputes_formula_server_side(): void
    {
        [$form, $requester] = $this->makeLeaveFormWithWorkflow();

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/mobile/forms/{$form->form_key}/draft", [
            'fields' => [
                'date_from' => '2026-06-10',
                'date_to' => '2026-06-11',
                'total_days' => 999,
            ],
        ])->assertCreated();

        $submission = DocumentFormSubmission::query()->where('form_id', $form->id)->firstOrFail();
        $this->assertSame('draft', $submission->status);
        $this->assertSame(2.0, (float) $submission->payload['total_days']);
    }

    public function test_mobile_draft_update_recomputes_formula_server_side(): void
    {
        [$form, $requester] = $this->makeLeaveFormWithWorkflow();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $requester->id,
            'payload' => ['date_from' => '2026-06-10', 'date_to' => '2026-06-11', 'total_days' => 2.0],
            'status' => 'draft',
        ]);

        Sanctum::actingAs($requester);

        $this->putJson("/api/v1/mobile/submissions/{$submission->id}/draft", [
            'fields' => [
                'date_from' => '2026-06-10',
                'date_to' => '2026-06-14',
                'total_days' => 999,
            ],
        ])->assertOk();

        $this->assertSame(5.0, (float) $submission->fresh()->payload['total_days']);
    }

    /**
     * Leave-style form: date_from + date_to + formula total_days = DAYS(...),
     * bound to a single-stage workflow via a global form policy.
     *
     * @return array{0: DocumentForm, 1: User, 2: ApprovalWorkflow}
     */
    private function makeLeaveFormWithWorkflow(): array
    {
        $requester = $this->makeRegularUser('mobile-formula-'.uniqid().'@example.test');
        $approver = $this->makeRegularUser('mobile-formula-approver-'.uniqid().'@example.test');

        $form = DocumentForm::factory()->create([
            'form_key' => 'mobile_formula_'.uniqid(),
            'document_type' => 'leave_request',
            'is_active' => true,
        ]);

        foreach (['date_from', 'date_to'] as $i => $key) {
            DocumentFormField::query()->create([
                'form_id' => $form->id,
                'field_key' => $key,
                'label' => $key,
                'field_type' => 'date',
                'is_required' => true,
                'sort_order' => $i + 1,
                'editable_by' => ['requester'],
            ]);
        }
        DocumentFormField::query()->create([
            'form_id' => $form->id,
            'field_key' => 'total_days',
            'label' => 'Total Days',
            'field_type' => 'formula',
            'is_required' => false,
            'sort_order' => 3,
            'options' => ['expression' => 'DAYS(date_from, date_to)', 'decimals' => 0],
            'editable_by' => ['requester'],
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Leave default (mobile formula test)',
            'document_type' => 'leave_request',
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $workflow->id, 'step_no' => 1, 'name' => 'Step 1',
            'approver_type' => 'user', 'approver_ref' => (string) $approver->id,
            'min_approvals' => 1, 'is_active' => true,
        ]);
        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'department_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        return [$form->fresh('fields'), $requester, $workflow];
    }
}
