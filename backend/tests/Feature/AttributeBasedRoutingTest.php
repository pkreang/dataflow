<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentForm;
use App\Models\DocumentFormWorkflowPolicy;
use App\Models\DocumentFormWorkflowRange;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AttributeBasedRoutingTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalFlowService $svc;

    private ReflectionMethod $evalMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ApprovalFlowService::class);
        $rm = new ReflectionMethod(ApprovalFlowService::class, 'evalFieldCondition');
        $rm->setAccessible(true);
        $this->evalMethod = $rm;
    }

    private function eval(mixed $val, string $op, mixed $expected): bool
    {
        return $this->evalMethod->invoke($this->svc, $val, $op, $expected);
    }

    // ──────────────────────────────────────────────────────────────
    // Operator unit tests (via reflection)
    // ──────────────────────────────────────────────────────────────

    public function test_equals_operator_matches(): void
    {
        $this->assertTrue($this->eval('IT', '=', 'IT'));
    }

    public function test_equals_operator_no_match(): void
    {
        $this->assertFalse($this->eval('HR', '=', 'IT'));
    }

    public function test_not_equals_operator(): void
    {
        $this->assertTrue($this->eval('HR', '!=', 'IT'));
        $this->assertFalse($this->eval('IT', '!=', 'IT'));
    }

    public function test_greater_than_operator(): void
    {
        $this->assertTrue($this->eval('60000', '>', '50000'));
        $this->assertFalse($this->eval('50000', '>', '50000'));
        $this->assertFalse($this->eval('40000', '>', '50000'));
    }

    public function test_greater_than_or_equal_operator(): void
    {
        $this->assertTrue($this->eval('50000', '>=', '50000'));
        $this->assertTrue($this->eval('60000', '>=', '50000'));
        $this->assertFalse($this->eval('49999', '>=', '50000'));
    }

    public function test_less_than_operator(): void
    {
        $this->assertTrue($this->eval('5000', '<', '10000'));
        $this->assertFalse($this->eval('10000', '<', '10000'));
        $this->assertFalse($this->eval('15000', '<', '10000'));
    }

    public function test_less_than_or_equal_operator(): void
    {
        $this->assertTrue($this->eval('10000', '<=', '10000'));
        $this->assertTrue($this->eval('9999', '<=', '10000'));
        $this->assertFalse($this->eval('10001', '<=', '10000'));
    }

    public function test_in_operator(): void
    {
        $this->assertTrue($this->eval('HR', 'in', ['HR', 'FIN']));
        $this->assertTrue($this->eval('FIN', 'in', ['HR', 'FIN']));
        $this->assertFalse($this->eval('IT', 'in', ['HR', 'FIN']));
    }

    public function test_not_in_operator(): void
    {
        $this->assertTrue($this->eval('IT', 'not_in', ['HR', 'FIN']));
        $this->assertFalse($this->eval('HR', 'not_in', ['HR', 'FIN']));
    }

    public function test_contains_operator(): void
    {
        $this->assertTrue($this->eval('urgent fix needed', 'contains', 'urgent'));
        $this->assertTrue($this->eval('urgent', 'contains', 'urgent'));
        $this->assertFalse($this->eval('normal fix', 'contains', 'urgent'));
    }

    public function test_unknown_operator_returns_false(): void
    {
        $this->assertFalse($this->eval('IT', 'xyz', 'IT'));
        $this->assertFalse($this->eval('100', 'between', '50'));
    }

    public function test_numeric_string_comparison_for_non_numeric_values(): void
    {
        // non-numeric value with > returns false (numVal is null)
        $this->assertFalse($this->eval('abc', '>', '0'));
        $this->assertFalse($this->eval('abc', '<', '999'));
    }

    // ──────────────────────────────────────────────────────────────
    // Integration tests via resolveWorkflowId (through start())
    // ──────────────────────────────────────────────────────────────

    private function makeRequester(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'Requester',
            'email' => 'requester-attr@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeApprover(string $email = 'approver-attr@example.com'): User
    {
        return User::create([
            'first_name' => 'App',
            'last_name' => 'Rover',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeWorkflow(string $docType, User $approver): ApprovalWorkflow
    {
        $wf = ApprovalWorkflow::create([
            'name' => 'WF-'.uniqid(),
            'document_type' => $docType,
            'is_active' => true,
        ]);
        ApprovalWorkflowStage::create([
            'workflow_id' => $wf->id,
            'step_no' => 1,
            'name' => 'Approve',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'is_active' => true,
        ]);

        return $wf;
    }

    private function makeFormAndPolicy(
        string $docType,
        array $fieldConditions,
        ?int $defaultWorkflowId = null,
        bool $useAmountCondition = false
    ): void {
        $form = DocumentForm::create([
            'form_key' => 'test_attr_form_'.uniqid(),
            'name' => 'Test Attr Form',
            'document_type' => $docType,
            'is_active' => true,
        ]);

        DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'use_amount_condition' => $useAmountCondition,
            'field_conditions' => $fieldConditions,
            'workflow_id' => $defaultWorkflowId,
        ]);

        // store form_key so caller can use it
        $this->formKey = $form->form_key;
    }

    private string $formKey = '';

    public function test_field_condition_routes_to_matching_workflow(): void
    {
        $docType = 'attr_test_'.uniqid();
        $approver = $this->makeApprover();
        $requester = $this->makeRequester();

        $wfA = $this->makeWorkflow($docType, $approver);
        $wfB = $this->makeWorkflow($docType, $approver);

        $this->makeFormAndPolicy($docType, [
            ['field_key' => 'purchase_type', 'operator' => '=', 'value' => 'IT', 'workflow_id' => $wfA->id, 'priority' => 1],
        ], $wfB->id);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            requesterUserId: $requester->id,
            payload: ['purchase_type' => 'IT'],
            formKey: $this->formKey,
        );

        $this->assertSame($wfA->id, $instance->workflow_id);
    }

    public function test_no_field_match_falls_back_to_default_workflow(): void
    {
        $docType = 'attr_test_'.uniqid();
        $approver = $this->makeApprover('approver2@example.com');
        $requester = $this->makeRequester();

        $wfA = $this->makeWorkflow($docType, $approver);
        $wfDefault = $this->makeWorkflow($docType, $approver);

        $this->makeFormAndPolicy($docType, [
            ['field_key' => 'purchase_type', 'operator' => '=', 'value' => 'IT', 'workflow_id' => $wfA->id, 'priority' => 1],
        ], $wfDefault->id);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            requesterUserId: $requester->id,
            payload: ['purchase_type' => 'HR'],
            formKey: $this->formKey,
        );

        $this->assertSame($wfDefault->id, $instance->workflow_id);
    }

    public function test_priority_lower_number_wins(): void
    {
        $docType = 'attr_test_'.uniqid();
        $approver = $this->makeApprover('approver3@example.com');
        $requester = $this->makeRequester();

        $wfFirst = $this->makeWorkflow($docType, $approver);
        $wfSecond = $this->makeWorkflow($docType, $approver);

        $this->makeFormAndPolicy($docType, [
            ['field_key' => 'dept', 'operator' => '=', 'value' => 'HR', 'workflow_id' => $wfSecond->id, 'priority' => 2],
            ['field_key' => 'dept', 'operator' => 'in', 'value' => ['HR', 'FIN'], 'workflow_id' => $wfFirst->id, 'priority' => 1],
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            requesterUserId: $requester->id,
            payload: ['dept' => 'HR'],
            formKey: $this->formKey,
        );

        // priority=1 matches first (in operator) even though priority=2 (=) would also match
        $this->assertSame($wfFirst->id, $instance->workflow_id);
    }

    public function test_field_conditions_take_priority_over_amount_ranges(): void
    {
        $docType = 'attr_test_'.uniqid();
        $approver = $this->makeApprover('approver4@example.com');
        $requester = $this->makeRequester();

        $wfField = $this->makeWorkflow($docType, $approver);
        $wfAmount = $this->makeWorkflow($docType, $approver);

        $form = DocumentForm::create([
            'form_key' => 'test_field_amount_'.uniqid(),
            'name' => 'Test FA Form',
            'document_type' => $docType,
            'is_active' => true,
        ]);

        $policy = DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'use_amount_condition' => true,
            'amount_field_key' => 'total',
            'field_conditions' => [
                ['field_key' => 'category', 'operator' => '=', 'value' => 'urgent', 'workflow_id' => $wfField->id, 'priority' => 1],
            ],
        ]);

        DocumentFormWorkflowRange::create([
            'policy_id' => $policy->id,
            'min_amount' => 0,
            'max_amount' => null,
            'workflow_id' => $wfAmount->id,
            'sort_order' => 1,
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            requesterUserId: $requester->id,
            payload: ['category' => 'urgent', 'total' => 50000],
            formKey: $form->form_key,
            amount: 50000.0,
        );

        // field_condition matched → use wfField (not amount range's wfAmount)
        $this->assertSame($wfField->id, $instance->workflow_id);
    }

    public function test_missing_field_in_payload_skips_condition_and_uses_next(): void
    {
        $docType = 'attr_test_'.uniqid();
        $approver = $this->makeApprover('approver5@example.com');
        $requester = $this->makeRequester();

        $wfMissing = $this->makeWorkflow($docType, $approver);
        $wfFallback = $this->makeWorkflow($docType, $approver);

        $this->makeFormAndPolicy($docType, [
            // field_key "nonexistent" is not in payload → skip
            ['field_key' => 'nonexistent', 'operator' => '=', 'value' => 'X', 'workflow_id' => $wfMissing->id, 'priority' => 1],
            // field_key "category" IS in payload
            ['field_key' => 'category', 'operator' => '=', 'value' => 'normal', 'workflow_id' => $wfFallback->id, 'priority' => 2],
        ]);

        $instance = app(ApprovalFlowService::class)->start(
            documentType: $docType,
            requesterUserId: $requester->id,
            payload: ['category' => 'normal'],
            formKey: $this->formKey,
        );

        $this->assertSame($wfFallback->id, $instance->workflow_id);
    }
}
