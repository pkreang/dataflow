<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStage;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Coverage for the per-stage `require_signature` flag flowing through
 * ApprovalFlowService::act() — both the blocking path (throws when no
 * signature is supplied) and the optional path (passes when the stage
 * doesn't require a signature).
 *
 * Sister to QrTemplateResolverTest + EvaluateRulesPhpTest for completeness
 * of the new approver-signature feature.
 */
class ApprovalSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_blocks_when_require_signature_and_no_signature_supplied(): void
    {
        [$instance, $approver] = $this->makeWorkflowWithStep(requireSignature: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('signature_required');

        app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved', 'OK', null);
    }

    public function test_rejection_also_blocks_when_signature_required(): void
    {
        // Reject is also a formal action — must require signature when stage demands it.
        [$instance, $approver] = $this->makeWorkflowWithStep(requireSignature: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('signature_required');

        app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'rejected', 'no', null);
    }

    public function test_approval_passes_without_signature_when_stage_does_not_require(): void
    {
        [$instance, $approver] = $this->makeWorkflowWithStep(requireSignature: false);

        $fresh = app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved', 'OK', null);

        $this->assertSame('approved', $fresh->status);
        $step = $fresh->steps->firstWhere('step_no', 1);
        $this->assertSame('approved', $step->action);
        // approved_by[].signature should be null/absent (depending on JSON
        // serializer behavior) when none supplied — either is acceptable.
        $entry = $step->approved_by[0] ?? [];
        $this->assertSame($approver->id, $entry['user_id'] ?? null);
        $this->assertEmpty($entry['signature'] ?? null);
    }

    public function test_supplied_signature_is_persisted_on_approve(): void
    {
        [$instance, $approver] = $this->makeWorkflowWithStep(requireSignature: true);
        $sig = 'data:image/png;base64,FAKEPAYLOAD';

        $fresh = app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'approved', 'OK', $sig);

        $step = $fresh->steps->firstWhere('step_no', 1);
        $this->assertSame($sig, $step->approved_by[0]['signature'] ?? null);
    }

    public function test_supplied_signature_is_persisted_on_reject(): void
    {
        [$instance, $approver] = $this->makeWorkflowWithStep(requireSignature: true);
        $sig = 'data:image/png;base64,REJECTPAYLOAD';

        $fresh = app(ApprovalFlowService::class)->act($instance->id, $approver->id, 'rejected', 'no', $sig);

        $step = $fresh->steps->firstWhere('step_no', 1);
        $this->assertSame($sig, $step->signature_image);
    }

    /**
     * Build a minimal one-step workflow with `require_signature` configurable,
     * along with an approver user assigned via approver_type=user.
     *
     * @return array{0: ApprovalInstance, 1: User}
     */
    private function makeWorkflowWithStep(bool $requireSignature): array
    {
        static $counter = 0;
        $counter++;

        $approver = User::query()->create([
            'first_name' => 'Sig',
            'last_name' => "Approver{$counter}",
            'email' => "sig_approver_{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
        $requester = User::query()->create([
            'first_name' => 'Sig',
            'last_name' => "Requester{$counter}",
            'email' => "sig_requester_{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'name' => "Sig WF {$counter}",
            'document_type' => 'sig_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);
        ApprovalWorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'step_no' => 1,
            'name' => 'Approver',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'require_signature' => $requireSignature,
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::query()->create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $requester->id,
            'document_type' => 'sig_test',
            'reference_no' => "SIG-{$counter}",
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);
        ApprovalInstanceStep::query()->create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Approver',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'min_approvals' => 1,
            'require_signature' => $requireSignature,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        return [$instance->fresh(['steps', 'workflow']), $approver];
    }
}
